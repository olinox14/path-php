Feature: Path

  Scenario: I instantiate a new Path object
    Given that I have a valid path as a string
    When I pass it as a parameter to the Path constructor
    Then I get a new Path object

  Scenario: I get the string representation of a Path object
    Given that I have a Path object
    When I cast it into a string
    Then I get the path that this object took as a constructor's parameter

