<?php
require_once __DIR__ . '/../lib/utils.php';

class SignUp extends RawDataContainer {
  public function verify(): SignUpInfo {
    $session = $this->get('session');
    $postData = $this->get('post-data');
    $cookie = $this->get('cookie');
    $urlQuery = $this->get('url-query');

    if ($postData->getDefault('signed-up', 'off') === 'on') {
      $signup = static::checkSignUp([
        'fullname' => $postData->getDefault('fullname', ''),
        'username' => $postData->getDefault('username', ''),
        'password' => $postData->getDefault('password', ''),
        're-password' => $postData->getDefault('re-password', ''),
      ]);

      if ($signup->succeed()) {
        $session->without([
          'fullname',
          'username',
          'password',
          're-password',
        ])->update();

        $session->without([
          'sign-up-succeed',
          'sign-up-error-field',
          'sign-up-error-reason',
        ])->update();

        $urlQuery->set('page', $urlQuery->get('previous-page'))->redirect();
      } else {
        $error = $signup->error();

        $session->assign([
          'sign-up-succeed' => 'off',
          'sign-up-error-field' => $error['field'],
          'sign-up-error-reason' => $error['reason'],
        ])->update();
      }
    }

    return new SignUpInfo();
  }

  static public function checkSignUp(array $param): SignUpInfo {
    [
      'fullname' => $fullname,
      'username' => $username,
      'password' => $password,
      're-password' => $rePassword,
    ] = $param;

    if (!$fullname) return SignUpInfo::mkerror('fullname', 'empty');
    if (!$username) return SignUpInfo::mkerror('username', 'empty');
    if (!$password) return SignUpInfo::mkerror('password', 'empty');
    if (strlen($password) < 6) return SignUpInfo::mkerror('password', 'insufficient-lenth');
    if ($password !== $rePassword) return SignUpInfo::mkerror('re-password', 'mismatch');

    return SignUpInfo::instance($param);
  }
}

class SignUpInfo extends RawDataContainer {
  public function succeed(): bool {
    return $this->getDefault('succeed', false);
  }

  public function error(): ?array {
    return $this->getDefault('error', null);
  }

  public function login(): ?LoginInfo {
    return $this->getDefault('login', null);
  }

  static public function mkerror(string $field, string $reason): self {
    return self::instance([
      'field' => $field,
      'reason' => $reason,
    ]);
  }
}
?>
