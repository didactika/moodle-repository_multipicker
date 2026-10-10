<div align="center">

<img src="pix/icon.svg" width="96" alt="MultiPicker icon">

# MultiPicker for Moodle

*Select several Server files and folders in one import*

[![Release](https://img.shields.io/github/v/release/didactika/moodle-repository_multipicker?style=flat-square)](https://github.com/didactika/moodle-repository_multipicker/releases)
[![Moodle](https://img.shields.io/badge/Moodle-5.3-f98012?style=flat-square&logo=moodle&logoColor=white)](https://moodle.org)
[![PHP](https://img.shields.io/badge/PHP-8.3+-777bb4?style=flat-square&logo=php&logoColor=white)](https://www.php.net)
[![License](https://img.shields.io/badge/License-GPL_v3-blue?style=flat-square)](LICENSE)

[Overview](#overview) · [Installation](#installation) · [Usage](#usage) · [Configuration](#configuration) · [Troubleshooting](#troubleshooting)

</div>

MultiPicker (**repository_multipicker**) is a standalone repository that adds multiple selection to the files available through Moodle's Server files browser. It copies several files and recursive folders into the current native filemanager draft without requiring a local plugin, catalog, relationship profile or scheduled task.

> [!IMPORTANT]
> This is the new standalone MultiPicker. Existing installations of the previous CourseFiles solution under `repository_multipicker` must complete the [CourseFiles adoption](https://github.com/didactika/moodle-local_coursefiles#existing-multipicker-installations) before installing it.

## Overview

- Select files and folders together; recursive expansion removes overlapping source occurrences.
- Browse one directory at a time with pages of 30 nodes.
- Review all resulting paths before copying; author and licence remain those of the source.
- Existing identical paths are skipped; different-content path conflicts block the entire import.
- Revalidate source access, current draft content, accepted types, file count and byte limits before copying.
- Session approvals expire after one hour and are consumed after a successful copy.
- Native single-file controls retain Server files behaviour; multiple selection is available in native filemanagers.

## Installation

Place this distribution in `repository/multipicker`, run Moodle's upgrade and enable MultiPicker under **Site administration → Plugins → Repositories → Manage repositories**. Install no local companion.

```bash
git clone --branch main https://github.com/didactika/moodle-repository_multipicker.git repository/multipicker
php admin/cli/upgrade.php --non-interactive
```

On Moodle versions with a `public/` web root, install under `public/repository/multipicker`. The Moodle 5.3 distribution is maintained separately on `main`; it uses typed frontend integration. Compatibility verification is recorded in the central workspace documentation; this initial distribution is beta until its complete matrix passes.

## Usage

1. Open a native filemanager and choose MultiPicker in the file picker.
2. Navigate through accessible Server files directories, checking files or folders. Selection remains across pages and directories.
3. Choose **Review selection** to inspect the resulting files and destination paths.
4. Choose **Import files** to copy the approved batch into the native draft, then save the owning Moodle form normally.

## Configuration

The repository instance's configured name appears in the chooser. The `repository/multipicker:view` capability is granted to editing teachers and managers by default. Access to individual sources remains controlled by Moodle's file-browser permissions; this repository does not grant additional access to other courses or private material.

File count comes from the owning native filemanager's client options and is checked during review/import; the owning Moodle form remains the authority for validation when saved. Accepted types and byte limits are also retained in the server session. Limit metadata never replaces source authorization.

## Troubleshooting

| Problem | Check |
|---|---|
| A directory is absent | The same core file-browser permissions apply as in Server files. |
| Selection exceeds a limit | Remove files or directories, or change the owning form's permitted limits. |
| Destination path conflict | Clear the selection and select only the required files, or use another native destination directory. |
| Approval expired or source changed | Open the chooser again and review the current source files. |

## Getting help

Open an [issue](https://github.com/didactika/moodle-repository_multipicker/issues) with the Moodle/PHP version, source activity and native destination form. Plugin-owned persistent personal data is not stored; copied files use Moodle's normal draft lifecycle.
