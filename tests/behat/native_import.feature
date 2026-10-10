@repository @repository_multipicker @javascript
Feature: Import a standalone Server files batch into a native folder
  As an editing teacher
  I need multiple files and recursive directories without a local companion

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
