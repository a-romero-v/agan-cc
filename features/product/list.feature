Feature: List the product catalogue

  Scenario: The catalogue is public and ordered by name
    Given the following products exist:
      | id                                   | name                 | price |
      | 019a7f3e-4a0b-7b29-8c1d-000000000001 | Espresso Cup         | 999   |
      | 019a7f3e-4a0b-7b29-8c1d-000000000002 | Coffee Filter Papers | 100   |
    When I send a GET request to "/products"
    Then the response status code should be 200
    And the JSON response is:
      """
      [
        {
          "id": "019a7f3e-4a0b-7b29-8c1d-000000000002",
          "name": "Coffee Filter Papers",
          "price": {"amount": 100, "currency": "EUR"},
          "taxRateBp": 1900
        },
        {
          "id": "019a7f3e-4a0b-7b29-8c1d-000000000001",
          "name": "Espresso Cup",
          "price": {"amount": 999, "currency": "EUR"},
          "taxRateBp": 1900
        }
      ]
      """
    And the response matches the OpenAPI spec

  # Runs after a scenario that created products, so it also proves the reset between scenarios.
  Scenario: An empty catalogue is an empty list
    When I send a GET request to "/products"
    Then the response status code should be 200
    And the JSON response is:
      """
      []
      """
    And the response matches the OpenAPI spec
