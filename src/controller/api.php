<?php
require_once __DIR__ . '/../lib/utils.php';

class ApplicationProgrammingInterface extends LazyLoadedDataContainer {
  protected function load(): array {
    $param = $this->param;

    $filterKeys = function (array $list, array $fields) {
      return array_map(
        function (array $row) use($fields) {
          return array_filter(
            $row,
            function (string $key) use($fields) {
              return in_array($key, $fields);
            },
            ARRAY_FILTER_USE_KEY
          );
        },
        $list
      );
    };

    return [
      'allGames' => function ($fields) use($param, $filterKeys) {
        $type = gettype($fields);
        if ($type !== 'array') {
          return ApiResponse::failure([
            'path' => [],
            'expected' => ['type' => 'array'],
            'received' => ['type' => $type],
          ]);
        }

        return ApiResponse::success(
          $filterKeys($param->get('game-manager')->list(), $fields)
        );
      },
    ];
  }

  public function getResponse(): ApiResponse {
    $self = $this;
    $inputString = file_get_contents('php://input');
    $inputJson = json_decode($inputString);

    if ($inputJson === false) {
      return ApiResponse::failure([
        'path' => [],
        'reason' => 'Failed to parse input string as JSON',
        'inputString' => $inputString,
        'errorCode' => json_last_error(),
        'errorMessage' => json_last_error_msg(),
      ]);
    }

    $type = gettype($inputJson);
    if ($type !== 'object') {
      return self::invalidType([], 'object', $type, [
        'inputString' => $inputString,
        'inputJson' => $inputJson,
      ]);
    }

    [$payload, $error] = [[], []];

    foreach ($inputJson as $fname => $fparam) {
      $func = $this->getDefault($fname, null);

      if (!$func) {
        array_push($error, [
          'path' => [$fname],
          'reason' => 'Function does not exist',
          'inputString' => $inputString,
        ]);

        $payload[$fname] = null;

        continue;
      }

      $response = $func($fparam);

      $error = array_merge($error, array_map(
        function (array $error) use($fparam, $fname) {
          return array_merge($error, [
            'path' => array_merge(
              [$fname],
              array_key_exists('path', $error) ? $error['path'] : []
            ),
            'inputJson' => $fparam,
          ]);
        },

        $response->error()
      ));

      $payload[$fname] = $response->payload();
    }

    return ApiResponse::success($payload, $error);
  }

  static private function invalidType($path, $expected, $received, array $rest = []): ApiFailureResponse {
    return ApiResponse::failure(array_merge($rest, [
      'reason' => 'Invalid Type',
      'path' => $path,
      'expected' => ['type' => $expected],
      'received' => ['type' => $received],
    ]));
  }

  public function getResponseString(): string {
    $param = $this->param;
    $urlQuery = $param->get('url-query');
    $prettyPrint = $urlQuery->getDefault('pretty-print', 'off') === 'on';
    $response = $this->getResponse();
    return $response->encode($prettyPrint ? JSON_PRETTY_PRINT : 0);
  }
}

class ApiResponse extends RawDataContainer {
  public function encode(int $flags = 0): string {
    return json_encode($this->getData(), $flags);
  }

  public function payload() {
    return $this->getDefault('payload', null);
  }

  public function error() {
    return $this->getDefault('error', []);
  }

  static public function success($payload, $error = null): self {
    return static::instance([
      'payload' => $payload,
      'error' => $error ? [$error] : [],
    ]);
  }

  static public function failure($error, $payload = null): self {
    return static::success($payload, $error);
  }
}
?>
