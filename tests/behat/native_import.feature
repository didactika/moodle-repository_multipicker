@repository @repository_multipicker @javascript
Feature: Import a standalone Server files batch into a native folder
  As an editing teacher
  I need multiple files and recursive directories without a local companion

  @picker_presentation
  Scenario: Switching repositories restores the native header and content size
    Given standalone MultiPicker test materials exist
    And I am on the "Destination files" "folder activity" page logged in as "spteacher"
    When I press "Edit"
    And I open standalone MultiPicker
    Then standalone presentation should follow the active native repository
    When I select the standalone source directory contents
    And I review standalone MultiPicker files
    And I import standalone MultiPicker files
    And I press "Save changes"
    Then I should see "first.txt"

  Scenario: Native aliases retain both files and their source declarations
    Given standalone MultiPicker test materials exist
    And I am on the "Destination files" "folder activity" page logged in as "spteacher"
    When I press "Edit"
    And I open standalone MultiPicker
    And I select the standalone source directory contents
    And I choose standalone file aliases
    And I review standalone MultiPicker files
    And I import standalone MultiPicker files
    And I press "Save changes"
    Then the standalone destination contains two aliases

  Scenario: A mixed selection is copied and saved by the native owning form
    Given standalone MultiPicker test materials exist
    And I am on the "Destination files" "folder activity" page logged in as "spteacher"
    When I press "Edit"
    And I open standalone MultiPicker
    And I select the standalone source directory contents
    And I review standalone MultiPicker files
    And I import standalone MultiPicker files
    And I press "Save changes"
    Then I should see "existing.txt"
    And I should see "first.txt"
    And I should see "nested"

  Scenario: Selection survives cards list and lazy tree navigation
    Given standalone MultiPicker test materials exist
    And I am on the "Destination files" "folder activity" page logged in as "spteacher"
    When I press "Edit"
    And I open standalone MultiPicker
    And I select the standalone source directory contents
    Then standalone selection remains consistent across all views
    When I review standalone MultiPicker files
    And I import standalone MultiPicker files
    And I press "Save changes"
    Then I should see "first.txt"
    And I should see "nested"

  Scenario: A second batch can reuse the native filemanager browsing scope
    Given standalone MultiPicker test materials exist
    And I am on the "Destination files" "folder activity" page logged in as "spteacher"
    When I press "Edit"
    And I open standalone MultiPicker
    And I select the standalone source directory contents
    And I review standalone MultiPicker files
    And I import standalone MultiPicker files
    And I open standalone MultiPicker
    Then the reopened standalone browser is ready

  Scenario: Search and clear preserve the native recursive selection
    Given standalone MultiPicker test materials exist
    And I am on the "Destination files" "folder activity" page logged in as "spteacher"
    When I press "Edit"
    And I open standalone MultiPicker
    And I select the standalone source directory contents
    Then standalone search preserves and clears its restriction

  Scenario: Review folders select removals without disabling import
    Given standalone MultiPicker test materials exist
    And I am on the "Destination files" "folder activity" page logged in as "spteacher"
    When I press "Edit"
    And I open standalone MultiPicker
    And I select the standalone source directory contents
    And I review standalone MultiPicker files
    And I remove the nested standalone review folder
    And I import standalone MultiPicker files
    And I press "Save changes"
    Then I should see "first.txt"
    And I should not see "second.txt"

  Scenario: Review lazily opens folders and deletes individual files
    Given standalone MultiPicker test materials exist
    And I am on the "Destination files" "folder activity" page logged in as "spteacher"
    When I press "Edit"
    And I open standalone MultiPicker
    And I select the standalone source directory contents
    And I review standalone MultiPicker files
    Then standalone review loads branches on demand and deletes individual files

  Scenario: Long names are clamped and source navigation shows a full route
    Given standalone MultiPicker test materials exist
    And standalone source has a long filename
    And I am on the "Destination files" "folder activity" page logged in as "spteacher"
    When I press "Edit"
    And I open standalone MultiPicker
    Then standalone long names occupy at most two lines in every view
