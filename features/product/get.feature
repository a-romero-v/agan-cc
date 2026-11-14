Feature: Get a product

  Background:
    Given the following products exist:
      | id                                   | name         | price |
      | 019a7f3e-4a0b-7b29-8c1d-000000000001 | Espresso Cup | 999   |

  Scenario: A product is public
    When I send a GET request to "/products/019a7f3e-4a0b-7b29-8c1d-000000000001"
    Then the response status code should be 200
    And the JSON response is:
      """
      {
        "id": "019a7f3e-4a0b-7b29-8c1d-000000000001",
        "name": "Espresso Cup",
        "price": {"amount": 999, "currency": "EUR"},
        "taxRateBp": 1900
      }
      """
    And the response matches the OpenAPI spec

  Scenario: An unknown product is not found
    When I send a GET request to "/products/019a7f3e-4a0b-7b29-8c1d-00000000ffff"
    Then the response status code should be 404
    And the JSON response is:
      """
      {
        "type": "about:blank",
        "title": "Not Found",
        "status": 404,
        "detail": "Product \"019a7f3e-4a0b-7b29-8c1d-00000000ffff\" not found.",
        "errorCode": 2001
      }
      """
    And the response matches the OpenAPI spec

  Scenario: An ID that is not a UUID never reaches the catalogue
    When I send a GET request to "/products/espresso-cup"
    Then the response status code should be 404
    And the JSON response is:
      """
      {
        "type": "about:blank",
        "title": "Not Found",
        "status": 404,
        "detail": "Resource not found.",
        "errorCode": 4001
      }
      """
