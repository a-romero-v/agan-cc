Feature: List the product catalogue
  The catalogue is never returned as a whole: the list is paginated.

  Scenario: The catalogue is public and ordered by name
    Given the following products exist:
      | id                                   | name                 | price |
      | 019a7f3e-4a0b-7b29-8c1d-000000000001 | Espresso Cup         | 999   |
      | 019a7f3e-4a0b-7b29-8c1d-000000000002 | Coffee Filter Papers | 100   |
    When I send a GET request to "/products"
    Then the response status code should be 200
    And the JSON response is:
      """
      {
        "items": [
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
        ],
        "page": 1,
        "perPage": 20,
        "total": 2
      }
      """
    And the response matches the OpenAPI spec

  # Runs after a scenario that created products, so it also proves the reset between scenarios.
  Scenario: An empty catalogue is an empty page
    When I send a GET request to "/products"
    Then the response status code should be 200
    And the JSON response is:
      """
      {"items": [], "page": 1, "perPage": 20, "total": 0}
      """
    And the response matches the OpenAPI spec

  Scenario: A page holds only the requested slice of the catalogue
    Given the following products exist:
      | id                                   | name                 | price |
      | 019a7f3e-4a0b-7b29-8c1d-000000000001 | Espresso Cup         | 999   |
      | 019a7f3e-4a0b-7b29-8c1d-000000000002 | Coffee Filter Papers | 100   |
      | 019a7f3e-4a0b-7b29-8c1d-000000000003 | Grinder              | 4999  |
    When I send a GET request to "/products?page=2&perPage=2"
    Then the response status code should be 200
    And the JSON response is:
      """
      {
        "items": [
          {
            "id": "019a7f3e-4a0b-7b29-8c1d-000000000003",
            "name": "Grinder",
            "price": {"amount": 4999, "currency": "EUR"},
            "taxRateBp": 1900
          }
        ],
        "page": 2,
        "perPage": 2,
        "total": 3
      }
      """
    And the response matches the OpenAPI spec

  Scenario: A page behind the last one is empty, not an error
    Given the following products exist:
      | id                                   | name         | price |
      | 019a7f3e-4a0b-7b29-8c1d-000000000001 | Espresso Cup | 999   |
    When I send a GET request to "/products?page=5"
    Then the response status code should be 200
    And the JSON response is:
      """
      {"items": [], "page": 5, "perPage": 20, "total": 1}
      """
    And the response matches the OpenAPI spec

  Scenario: Paging parameters outside the limits are rejected
    When I send a GET request to "/products?page=0&perPage=101"
    Then the response status code should be 422
    And the response header "Content-Type" should be "application/problem+json"
    And the JSON response is:
      """
      {
        "type": "about:blank",
        "title": "Unprocessable Content",
        "status": 422,
        "detail": "The request is invalid.",
        "errorCode": 4002,
        "violations": [
          {"field": "page", "message": "This value should be positive."},
          {"field": "perPage", "message": "This value should be between 1 and 100."}
        ]
      }
      """
    And the response matches the OpenAPI spec

  Scenario: A paging parameter that is not a number is rejected
    When I send a GET request to "/products?page=abc"
    Then the response status code should be 422
    And the JSON response is:
      """
      {
        "type": "about:blank",
        "title": "Unprocessable Content",
        "status": 422,
        "detail": "The request is invalid.",
        "errorCode": 4002,
        "violations": [
          {"field": "page", "message": "This value should be of type int."}
        ]
      }
      """
    And the response matches the OpenAPI spec
