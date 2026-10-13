# Changelog

## [Unreleased]

## [1.0.0-m503] - 2026-10-14

First release of the Moodle 5.3 line, on `main`. The Moodle 4.0 to 5.2 line is on `MOODLE_400_STABLE` (1.0.0-m400).

### Added

- Multiple selection in the Server files repository of Moodle: files and recursive folders can be selected together, including results from other pages, with cards, list and expandable tree views that load only the branch that is open.
- Search that keeps the selection and can be cleared on its own; keeping the source folders or placing the files in the current destination; copying files or creating aliases when the owning control supports references.
- A review of the resulting paths before importing: identical files are skipped and conflicting paths are reported; the source author and licence are kept and the file types, file count and storage limits of the owning form are respected.
- Colours and components that follow the active theme and its colour modes.
- The interface is written in TypeScript and ESM, with the React browser integration of Moodle 5.3.
