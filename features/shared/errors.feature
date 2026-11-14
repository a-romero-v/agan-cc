Feature: Errors are RFC 9457 Problem Details
  Every error response has the same shape and carries an errorCode,
  including the errors the framework raises before a controller runs.

  Scenario: An unknown route is a 404 Problem Details response
    When I send a GET request to "/no-such-route"
    Then the response status code should be 404
    And the response header "Content-Type" should be "application/problem+json"
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

  Scenario: A method the route does not support is a 405 with the allowed methods
    When I send a POST request to "/products"
    Then the response status code should be 405
    And the response header "Allow" should be "GET"
    And the JSON response is:
      """
      {
        "type": "about:blank",
        "title": "Method Not Allowed",
        "status": 405,
        "detail": "Method not allowed.",
        "errorCode": 4005
      }
      """

  Scenario: The error does not echo the request
    When I send a POST request to "/no-such-route/<script>alert(1)</script>" with body:
      """
      {"secret": "do-not-echo"}
      """
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

  Scenario: Error responses carry the hardening header
    When I send a GET request to "/no-such-route"
    Then the response header "X-Content-Type-Options" should be "nosniff"
