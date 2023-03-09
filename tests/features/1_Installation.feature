@baseScenarios
Feature: Plugin installation management
  In order to use the plugin
  As merchant
  I need to manage plugin installation

  @pluginIsDisabled
  Scenario: Install the plugin
    Given the plugin is not enabled
    And I enable the plugin
    Then the plugin is enabled
    And I am on the homepage
    And I should see "Realised with Shopware"

  @pluginIsEnabled
  Scenario: Uninstall the plugin
    Given the plugin is enabled
    And I disable the plugin
    Then the plugin is not enabled
    And I am on the homepage
    And I should see "Realised with Shopware"
    And I enable the plugin
    Then the plugin is enabled
