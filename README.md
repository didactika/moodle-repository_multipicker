<div align="center">

<img src="pix/icon.svg" width="96" alt="Server files MultiPicker icon">

# Server files MultiPicker for Moodle

*Select several Server files and folders in one import*

[![Release](https://img.shields.io/github/v/release/didactika/moodle-repository_multipicker?filter=v4.0.*&style=flat-square)](https://github.com/didactika/moodle-repository_multipicker/releases)
[![Moodle](https://img.shields.io/badge/Moodle-4.0%E2%80%935.2-f98012?style=flat-square&logo=moodle&logoColor=white)](https://moodle.org)
[![PHP](https://img.shields.io/badge/PHP-7.4+-777bb4?style=flat-square&logo=php&logoColor=white)](https://www.php.net)
[![License](https://img.shields.io/badge/License-GPL_v3-blue?style=flat-square)](LICENSE)

[Overview](#overview) • [Installation](#installation) • [Usage](#usage) • [Configuration](#configuration) • [Troubleshooting](#troubleshooting)

</div>

Server files MultiPicker (**repository_multipicker**) adds multiple selection to Moodle's Server files repository. Teachers can select several files and folders, review the resulting files and import them into the activity's normal filemanager. It works independently and needs no local companion.

## Overview

The repository uses the same accessible files, directories and search as Server files. It adds cards, list and expandable tree views with a shared selection. Folders remain collapsed until opened, and only the requested branch is loaded.

- Select files and recursive folders together, including results from other pages.
- Search without losing the selection, and clear the search independently.
- Keep the source folders or place the resulting files in the current destination directory.
- Copy files or create aliases when the owning control supports references.
- Review the resulting paths before importing; existing identical files are skipped and conflicting paths are reported.
- Preserve the source author and licence, and respect the owning form’s file types, file count and storage limits.

## Installation

**Requirements:** Moodle 4.0–5.2, PHP 7.4 or newer, and the database requirements of the selected Moodle release. This distribution’s release is **4.0.0**.

**From a release:** download the matching release ZIP, open **Site administration → Plugins → Install plugins**, upload the package and follow the prompts.

**From Git:**

```bash
cd /path/to/moodle
git clone --branch MOODLE_400_STABLE https://github.com/didactika/moodle-repository_multipicker.git repository/multipicker
php admin/cli/upgrade.php --non-interactive
```

Enable **Server files MultiPicker** under **Site administration → Plugins → Repositories → Manage repositories**.

## Usage

1. Open **Add files** in the activity’s filemanager and choose **Server files MultiPicker**.
2. Browse or search, and use cards, list or tree to select the files and folders you need.
3. Choose the import method and whether to keep folders.
4. Open **Review selection**, check the resulting files and choose **Import files**.
5. Save the owning Moodle form normally. Cancelling that form follows Moodle’s normal draft-file behavior.

## Configuration

The repository name comes from Moodle’s language strings and is always **Server files MultiPicker**. No custom instance name is required.

The **repository/multipicker:view** capability permits use of the chooser. Editing teachers and managers have it by default. Moodle’s File Browser permissions continue to decide which source files each user can access; repository access grants no extra access to private material.

Destination limits come from the owning filemanager. A folder that would exceed those limits is rejected before import. Aliases remain linked to their original source and are offered only when the control permits references.

Folder badges count the resulting selected files. The tree opens directories on demand and shares the same selection as cards and list. Folder preservation also retains the source-relative path of individually selected files; disable it to copy directly into the destination path.

## Large selections and long paths

Select all includes all accessible results in the current directory or search. Review starts with collapsed folders and loads their direct children on demand, 30 rows at a time. File counts cover the whole selection, and folder removal includes unopened descendants. Individual files and folders also have a remove icon.

The chooser respects native destination limits and bounds traversal to 500 selected roots, 10,000 visited nodes and 5,000 resulting files. Long source folder names are abbreviated consistently to fit Moodle's native destination path field. Very deep tails may be grouped under one abbreviated directory; filenames, content and source metadata are preserved. Approval and import always recheck live sources and the destination.

## Troubleshooting

| Problem | Check |
|---|---|
| A source is absent | The same File Browser permissions and file-type restrictions apply as in Server files |
| A selection exceeds a limit | Remove files or folders, or change the owning form’s allowed limits |
| A destination path conflicts | Clear that source from the selection or choose another destination directory |
| A session expired | Reopen the repository and review the current files before importing |

## Theme colours

Controls and badges use the active theme's Bootstrap components. Surfaces, selection and focus use native Bootstrap tokens without a plugin palette or fixed light-colour fallback. Embedded pickers inherit the owning page's mode and runtime Bootstrap overrides, including changes while the picker remains open.

This distribution retains native Moodle 4.0–5.2 theme behaviour. It does not add the experimental Moodle 5.3 mode switcher to older cores.

## Getting help

Open an [issue](https://github.com/didactika/moodle-repository_multipicker/issues) with your Moodle and PHP versions, the source activity, the destination form and the expected and actual behavior.
