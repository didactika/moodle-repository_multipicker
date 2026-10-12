@repository @repository_multipicker @native_theme @theme_toggle @javascript
Feature: Follow the owning Boost colour mode in standalone MultiPicker
  As a teacher using the experimental native colour modes
  I need the Server files chooser to use the native theme through review

  Scenario: The standalone document follows live native colour mode changes
    Given the following config values are set as admin:
      | enablecolourmodes | 1 | theme_boost |
    And standalone MultiPicker test materials exist
    And the following "user preferences" exist:
      | user      | preference             | value |
      | spteacher | theme_boost_colourmode | light |
    And I am on the "Destination files" "folder activity" page logged in as "spteacher"
    And I press "Edit"
    And I open standalone MultiPicker
    Then the standalone MultiPicker picker should inherit its native theme
    When I switch the standalone MultiPicker owning theme to "dark"
    Then the standalone MultiPicker picker should follow runtime Bootstrap changes
    When I switch the standalone MultiPicker owning theme to "light"
    And I switch the standalone MultiPicker owning theme to "auto"
    And I switch the standalone MultiPicker owning theme to "dark"
    And I select the standalone source directory contents
    And I review standalone MultiPicker files
    Then the standalone MultiPicker picker should inherit its native theme
    And I import standalone MultiPicker files
    And I press "Save changes"
    Then I should see "first.txt"
