//! PHP bindings for UmaDB event store
//!
//! This module provides PHP classes for interacting with UmaDB,
//! following the same patterns as the Python bindings.

use ext_php_rs::prelude::*;
use umadb_client::UmaDBClient as RustUmaDBClient;
use umadb_dcb::{
    DCBAppendCondition as RustAppendCondition, DCBError, DCBEvent as RustEvent,
    DCBEventStoreSync, DCBQuery as RustQuery, DCBQueryItem as RustQueryItem,
    DCBSequencedEvent as RustSequencedEvent,
};
use uuid::Uuid;

// ============================================================================
// Error Handling
// ============================================================================

/// Convert DCBError to PHP exception
///
/// For now, we use the standard PhpException with custom messages that indicate
/// the error type. In the future, these could be proper custom exception classes.
fn dcb_error_to_exception(err: DCBError) -> PhpException {
    match err {
        DCBError::IntegrityError(msg) => {
            PhpException::default(format!("UmaDB\\Exception\\IntegrityException: {}", msg))
        }
        DCBError::TransportError(msg) => {
            PhpException::default(format!("UmaDB\\Exception\\TransportException: {}", msg))
        }
        DCBError::Corruption(msg) => {
            PhpException::default(format!("UmaDB\\Exception\\CorruptionException: {}", msg))
        }
        DCBError::Io(err) => {
            PhpException::default(format!("UmaDB\\Exception\\IoException: {}", err))
        }
        _ => PhpException::default(format!("UmaDB\\Exception\\UmaDBException: {:?}", err)),
    }
}

// ============================================================================
// Event Class
// ============================================================================

/// Represents an event in the UmaDB event store.
///
/// # Properties
/// - `event_type` - The type/name of the event
/// - `data` - Binary event data
/// - `tags` - Array of tags for filtering
/// - `uuid` - Optional unique identifier for idempotency
#[derive(Clone, Debug)]
#[php_class]
#[php(name = "UmaDB\\Event")]
pub struct Event {
    /// The event type identifier
    pub event_type: String,
    /// Binary event data
    pub data: Vec<u8>,
    /// Tags for filtering and querying
    pub tags: Vec<String>,
    /// Optional UUID for idempotency
    pub uuid: Option<String>,
}

#[php_impl]
impl Event {
    /// Create a new Event
    ///
    /// # Parameters
    /// - `event_type` - The event type identifier
    /// - `data` - Binary event data (string in PHP)
    /// - `tags` - Optional array of tags
    /// - `uuid` - Optional UUID string
    pub fn __construct(
        event_type: String,
        data: String,
        tags: Option<Vec<String>>,
        uuid: Option<String>,
    ) -> Self {
        Self {
            event_type,
            data: data.into_bytes(),
            tags: tags.unwrap_or_default(),
            uuid,
        }
    }

    /// Get the event type
    #[php(getter)]
    pub fn get_event_type(&self) -> String {
        self.event_type.clone()
    }

    /// Get the event data as a string
    #[php(getter)]
    pub fn get_data(&self) -> String {
        String::from_utf8_lossy(&self.data).to_string()
    }

    /// Get the tags
    #[php(getter)]
    pub fn get_tags(&self) -> Vec<String> {
        self.tags.clone()
    }

    /// Get the UUID
    #[php(getter)]
    pub fn get_uuid(&self) -> Option<String> {
        self.uuid.clone()
    }

    /// String representation
    #[allow(non_snake_case)]
    pub fn __toString(&self) -> String {
        format!(
            "Event(type={}, tags={:?}, uuid={:?})",
            self.event_type, self.tags, self.uuid
        )
    }
}

impl Event {
    /// Convert to Rust DCBEvent
    fn to_dcb_event(&self) -> Result<RustEvent, PhpException> {
        let uuid = if let Some(uuid_str) = &self.uuid {
            Some(
                Uuid::parse_str(uuid_str)
                    .map_err(|e| PhpException::default(format!("Invalid UUID: {}", e)))?,
            )
        } else {
            None
        };

        Ok(RustEvent {
            event_type: self.event_type.clone(),
            data: self.data.clone(),
            tags: self.tags.clone(),
            uuid,
        })
    }
}

// ============================================================================
// SequencedEvent Class
// ============================================================================

/// Represents an event with its position in the event stream.
///
/// # Properties
/// - `event` - The Event object
/// - `position` - Position in the event stream
#[derive(Clone, Debug)]
#[php_class]
#[php(name = "UmaDB\\SequencedEvent")]
pub struct SequencedEvent {
    /// The event
    pub event: Event,
    /// Position in the stream
    pub position: u64,
}

#[php_impl]
impl SequencedEvent {
    /// Get the event
    #[php(getter)]
    pub fn get_event(&self) -> Event {
        self.event.clone()
    }

    /// Get the position
    #[php(getter)]
    pub fn get_position(&self) -> u64 {
        self.position
    }

    /// String representation
    #[allow(non_snake_case)]
    pub fn __toString(&self) -> String {
        format!("SequencedEvent(position={}, event={})", self.position, self.event.__toString())
    }
}

impl From<RustSequencedEvent> for SequencedEvent {
    fn from(seq_event: RustSequencedEvent) -> Self {
        let uuid = seq_event.event.uuid.map(|u| u.to_string());
        Self {
            event: Event {
                event_type: seq_event.event.event_type,
                data: seq_event.event.data,
                tags: seq_event.event.tags,
                uuid,
            },
            position: seq_event.position,
        }
    }
}

// ============================================================================
// QueryItem Class
// ============================================================================

/// A query item specifying event types and tags to match.
///
/// An event matches a query item if:
/// - Its type is in the item's types (or item has no types)
/// - All item tags are present in the event's tags
#[derive(Clone, Debug)]
#[php_class]
#[php(name = "UmaDB\\QueryItem")]
pub struct QueryItem {
    /// Event types to match (empty = all types)
    pub types: Vec<String>,
    /// Tags that must all be present
    pub tags: Vec<String>,
}

#[php_impl]
impl QueryItem {
    /// Create a new QueryItem
    ///
    /// # Parameters
    /// - `types` - Optional array of event types to match
    /// - `tags` - Optional array of tags (all must be present)
    pub fn __construct(types: Option<Vec<String>>, tags: Option<Vec<String>>) -> Self {
        Self {
            types: types.unwrap_or_default(),
            tags: tags.unwrap_or_default(),
        }
    }

    /// Get the types
    #[php(getter)]
    pub fn get_types(&self) -> Vec<String> {
        self.types.clone()
    }

    /// Get the tags
    #[php(getter)]
    pub fn get_tags(&self) -> Vec<String> {
        self.tags.clone()
    }
}

impl From<QueryItem> for RustQueryItem {
    fn from(item: QueryItem) -> Self {
        let mut query_item = RustQueryItem::new();
        if !item.types.is_empty() {
            query_item = query_item.types(item.types);
        }
        if !item.tags.is_empty() {
            query_item = query_item.tags(item.tags);
        }
        query_item
    }
}

// ============================================================================
// Query Class
// ============================================================================

/// A query for filtering events.
///
/// An event matches the query if it matches any of the query items (OR logic).
#[derive(Clone, Debug)]
#[php_class]
#[php(name = "UmaDB\\Query")]
pub struct Query {
    /// Query items (OR semantics)
    pub items: Vec<QueryItem>,
}

#[php_impl]
impl Query {
    /// Create a new Query
    ///
    /// # Parameters
    /// - `items` - Optional array of QueryItem objects
    pub fn __construct(items: Option<Vec<&QueryItem>>) -> Self {
        Self {
            items: items.unwrap_or_default().into_iter().map(|i| i.clone()).collect(),
        }
    }

    /// Get the query items
    #[php(getter)]
    pub fn get_items(&self) -> Vec<QueryItem> {
        self.items.clone()
    }
}

impl From<Query> for RustQuery {
    fn from(query: Query) -> Self {
        let mut rust_query = RustQuery::new();
        for item in query.items {
            rust_query = rust_query.item(item.into());
        }
        rust_query
    }
}

// ============================================================================
// AppendCondition Class
// ============================================================================

/// Condition for conditional appends.
///
/// Allows enforcing business rules and optimistic concurrency control.
///
/// # Properties
/// - `fail_if_events_match` - Query that must not match any existing events
/// - `after` - Optional position constraint
#[derive(Clone, Debug)]
#[php_class]
#[php(name = "UmaDB\\AppendCondition")]
pub struct AppendCondition {
    /// Query that must not match
    pub fail_if_events_match: Query,
    /// Position constraint
    pub after: Option<u64>,
}

#[php_impl]
impl AppendCondition {
    /// Create a new AppendCondition
    ///
    /// # Parameters
    /// - `fail_if_events_match` - Query object
    /// - `after` - Optional position (fail if events exist after this)
    pub fn __construct(fail_if_events_match: &Query, after: Option<u64>) -> Self {
        Self {
            fail_if_events_match: fail_if_events_match.clone(),
            after,
        }
    }

    /// Get the query
    #[php(getter)]
    pub fn get_fail_if_events_match(&self) -> Query {
        self.fail_if_events_match.clone()
    }

    /// Get the position constraint
    #[php(getter)]
    pub fn get_after(&self) -> Option<u64> {
        self.after
    }
}

impl From<AppendCondition> for RustAppendCondition {
    fn from(condition: AppendCondition) -> Self {
        let mut rust_condition = RustAppendCondition::new(condition.fail_if_events_match.into());
        if let Some(after) = condition.after {
            rust_condition = rust_condition.after(Some(after));
        }
        rust_condition
    }
}

// ============================================================================
// Client Class
// ============================================================================

/// UmaDB client for connecting to and interacting with an UmaDB server.
///
/// # Example
/// ```php
/// $client = new UmaDB\Client("http://localhost:50051");
/// $head = $client->head();
/// ```
#[php_class]
#[php(name = "UmaDB\\Client")]
pub struct Client {
    /// Internal Rust client
    inner: umadb_client::SyncUmaDBClient,
}

#[php_impl]
impl Client {
    /// Create a new UmaDB client and connect to the server.
    ///
    /// # Parameters
    /// - `url` - Server URL (e.g., "http://localhost:50051" or "https://server:50051")
    /// - `ca_path` - Optional path to CA certificate file for TLS
    /// - `batch_size` - Optional batch size for reading events
    ///
    /// # Throws
    /// - TransportException if connection fails
    pub fn __construct(
        url: String,
        ca_path: Option<String>,
        batch_size: Option<u32>,
    ) -> PhpResult<Self> {
        let mut client_builder = RustUmaDBClient::new(url);

        if let Some(path) = ca_path {
            client_builder = client_builder.ca_path(path);
        }

        if let Some(size) = batch_size {
            client_builder = client_builder.batch_size(size);
        }

        let inner = client_builder.connect().map_err(dcb_error_to_exception)?;

        Ok(Self { inner })
    }

    /// Read events from the event store.
    ///
    /// # Parameters
    /// - `query` - Optional Query object to filter events
    /// - `start` - Optional starting position
    /// - `backwards` - Read backwards from start position
    /// - `limit` - Optional maximum number of events to return
    /// - `subscribe` - Subscribe to new events (streaming)
    ///
    /// # Returns
    /// Array of SequencedEvent objects
    ///
    /// # Throws
    /// - UmaDBException on error
    pub fn read(
        &self,
        query: Option<&Query>,
        start: Option<u64>,
        backwards: Option<bool>,
        limit: Option<u32>,
        subscribe: Option<bool>,
    ) -> PhpResult<Vec<SequencedEvent>> {
        let rust_query = query.map(|q| q.clone().into());
        let backwards = backwards.unwrap_or(false);
        let subscribe = subscribe.unwrap_or(false);

        let mut response = self
            .inner
            .read(rust_query, start, backwards, limit, subscribe)
            .map_err(dcb_error_to_exception)?;

        let mut events = Vec::new();
        for result in response.by_ref() {
            let seq_event = result.map_err(dcb_error_to_exception)?;
            events.push(seq_event.into());
        }

        Ok(events)
    }

    /// Get the current head position of the event store.
    ///
    /// # Returns
    /// The position of the last event, or null if store is empty
    ///
    /// # Throws
    /// - UmaDBException on error
    pub fn head(&self) -> PhpResult<Option<u64>> {
        self.inner.head().map_err(dcb_error_to_exception)
    }

    /// Append events to the event store.
    ///
    /// # Parameters
    /// - `events` - Array of Event objects to append
    /// - `condition` - Optional AppendCondition for optimistic concurrency control
    ///
    /// # Returns
    /// Position of the last appended event
    ///
    /// # Throws
    /// - IntegrityException if append condition fails
    /// - UmaDBException on other errors
    pub fn append(
        &self,
        events: Vec<&Event>,
        condition: Option<&AppendCondition>,
    ) -> PhpResult<u64> {
        let rust_events: Result<Vec<RustEvent>, PhpException> = events
            .iter()
            .map(|e| e.to_dcb_event())
            .collect();

        let rust_events = rust_events?;
        let rust_condition = condition.map(|c| c.clone().into());

        self.inner
            .append(rust_events, rust_condition)
            .map_err(dcb_error_to_exception)
    }
}

// ============================================================================
// Module Initialization
// ============================================================================

/// PHP module initialization
#[php_module]
pub fn get_module(module: ModuleBuilder) -> ModuleBuilder {
    module
        .class::<Event>()
        .class::<SequencedEvent>()
        .class::<QueryItem>()
        .class::<Query>()
        .class::<AppendCondition>()
        .class::<Client>()
}
