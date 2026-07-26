# 🏗️ Architecture

## 🔄 Match Flow

```text
Validate teams and participants
→ Resolve pool and algorithm
→ Snapshot the selected algorithm options
→ Enforce external-ID idempotency
→ Start database transaction
→ Create pending match
→ Create or lock rating records
→ Normalize teams and competitor states
→ Calculate all rating changes
→ Save teams and participants
→ Update current ratings and statistics
→ Save rating history
→ Mark match processed
→ Commit
→ Dispatch events
```

## 🔒 Concurrency

Rating rows are locked during match processing. External IDs and rating identities have unique indexes. Transactions retry up to three times for transient conflicts.

## 🧮 Algorithm Boundary

Algorithms receive immutable data objects and do not know about Eloquent. This keeps mathematical logic testable outside Laravel and allows applications to add drivers without replacing persistence.

## 🧬 Polymorphism

Ratings and participations use morph relationships. Different Eloquent model classes can compete in the same package installation. Published migrations can use numeric, UUID, or ULID morph keys.

## 🗂️ Rating Identity

A current rating is uniquely identified by:

```text
rateable type
rateable ID
pool
algorithm
season key
```

## ↩️ Rollback Safety

Direct rollback is restricted to the latest result for every participant. This prevents reverting an old rating without recalculating later dependent results.
