# ❓ Frequently Asked Questions

## Does RatingKit support 2v2 and 3v3?

Yes. It supports any number of players on each team.

## Can a match contain more than two teams?

Yes. Use `record()` with multiple `Team` objects or `freeForAll()`.

## Can teams have different sizes?

Yes. Weighted-average team aggregation is the default.

## Can one user have several ratings?

Yes. Pool, algorithm, and season are all part of a rating identity.

## Which algorithm should a chess server use?

Glicko-2 is a strong default when rating uncertainty matters. Elo is simpler and more predictable. Separate pools should generally be used for variants and time controls.

## Which algorithm should a team game use?

Weng-Lin and Glicko-2 are useful uncertainty-aware options. Elo is suitable when simplicity is the priority.

## Which algorithm should a free-for-all use?

Plackett-Luce is the most directly placement-oriented bundled option.

## Does RatingKit recalculate old matches automatically?

No. Current writes are transactional, and the latest match can be safely voided. Recalculating an older result requires replaying the affected pool chronologically.

## Are FIDE, USCF, DWZ, EGF, and FIFA drivers officially certified?

No. They are configurable application-oriented adaptations.

## Can I add my own algorithm?

Yes. Implement `RatingAlgorithm` and register the driver in configuration or through the algorithm registry.
