<?php
require_once __DIR__ . '/system-requirements.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/login.php';
require_once __DIR__ . '/logout.php';
require_once __DIR__ . '/sign-up.php';
require_once __DIR__ . '/db-game-genre.php';
require_once __DIR__ . '/db-game.php';
require_once __DIR__ . '/db-genre.php';
require_once __DIR__ . '/db-user.php';
require_once __DIR__ . '/db-admin.php';
require_once __DIR__ . '/db-history.php';
require_once __DIR__ . '/db-favourite.php';
require_once __DIR__ . '/db-comment.php';
require_once __DIR__ . '/search-engine.php';
require_once __DIR__ . '/user-profile.php';
require_once __DIR__ . '/api.php';
require_once __DIR__ . '/../model/index.php';
require_once __DIR__ . '/../view/index.php';
require_once __DIR__ . '/../lib/constants.php';

function getThemeColorSet(Cookie $cookie): array {
  $themeName = $cookie->getDefault('theme', 'light');
  $themeColorSet = null;

  switch ($themeName) {
    case 'light':
      $themeColorSet = LightThemeColors::create();
      break;
    case 'dark':
      $themeColorSet = DarkThemeColors::create();
      break;
    default:
      return [
        'invalid' => true,
        'new-cookie' => $cookie->set('theme', 'light'),
      ];
  }

  return [
    'invalid' => false,
    'name' => $themeName,
    'colors' => $themeColorSet->getData(),
  ];
}

function switchPage(array $data): Page {
  switch ($data['page']) {
    case 'index':
    case 'profile':
    case 'explore':
    case 'favourite':
    case 'history':
    case 'genre':
    case 'play':
    case 'search':
      return MainPage::instance($data);
    case 'login':
      return LoginPage::instance($data);
    case 'logout':
      return LogoutPage::instance(array_merge($data, [
        'logout' => Logout::instance($data),
      ]));
    case 'sign-up':
      return SignUpPage::instance($data);
    case 'admin':
      return AdminPage::instance($data);
    default:
      throw new NotFoundException();
  }
}

function createSubpageList(LoginInfo $login): array {
  $customized = $login->isLoggedIn()
    ? [
      'profile' => 'Tài khoản',
      'favourite' => 'Yêu thích',
      'history' => 'Lịch sử',
    ]
    : [
      'explore' => 'Khám phá',
      'sign-up' => 'Tham gia',
    ]
  ;

  $result = [[
    'page' => 'index',
    'title' => 'Trang chủ',
    'href' => '.',
  ]];

  foreach ($customized as $page => $title) {
    $href = UrlQuery::instance(['page' => $page])->getUrlQuery();

    array_push($result, [
      'page' => $page,
      'title' => $title,
      'href' => $href,
    ]);
  }

  return $result;
}

function createAdminSubpageList() {
  $namemap = [
    'games' => 'Trò chơi',
    'users' => 'Người dùng',
    'comments' => 'Bình luận',
    'advanced' => 'Nâng cao',
  ];

  $result = [[
    'subpage' => 'dashboard',
    'title' => 'Bảng điều khiển',
    'href' => UrlQuery::instance(['page' => 'admin'])->getUrlQuery(),
  ]];

  $urlQuery = UrlQuery::instance(['page' => 'admin']);

  foreach ($namemap as $page => $title) {
    $href = $urlQuery->set('subpage', $page)->getUrlQuery();

    array_push($result, [
      'subpage' => $page,
      'title' => $title,
      'href' => $href,
    ]);
  }

  return $result;
}

function sendHtml(DataContainer $data): string {
  return switchPage($data->getData())->render();
}

function validateFileName(string $name): void {
  if (preg_match('/^\/|(^|\/)\.\.($|\/)/', $name)) {
    ErrorPage::status(403)->render();
    throw new NotFoundException();
  }
}

function getFilePath(UrlQuery $urlQuery): string {
  $name = $urlQuery->get('name');
  validateFileName($name);

  switch ($urlQuery->get('purpose')) {
    case 'ui':
      return __DIR__ . "/../resources/images/$name";
    case 'game-img':
      return __DIR__ . "/../storage/game-imgs/$name";
    case 'game-swf':
      return __DIR__ . "/../storage/game-swfs/$name";
    default:
      throw new NotFoundException();
  }
}

function sendFile(UrlQuery $urlQuery): string {
  $requiredkeys = ['name', 'mime', 'purpose'];
  foreach ($requiredkeys as $key) {
    if (!$urlQuery->hasKey($key)) return ErrorPage::status(400)->render();
  }

  $mime = $urlQuery->get('mime');
  $filename = getFilePath($urlQuery);
  if (!file_exists($filename)) throw new NotFoundException();

  header('Content-Type: ' . $mime);
  header('Content-Length: ' . filesize($filename));
  header('Content-Disposition: inline');
  readfile($filename);
  exit;
}

function sendAjax(ApplicationProgrammingInterface $api): string {
  $response = $api->getResponseString();
  header('Content-Type: application/json');
  return $response;
}

function sendAction(DataContainer $param): string {
  $urlQuery = $param->get('url-query');
  $postData = $param->get('post-data');
  $files = $param->get('files');
  $dbQuerySet = $param->get('db-query-set');
  $cookie = $param->get('cookie');
  $session = $param->get('session');
  $login = $param->get('login');
  $action = $urlQuery->getDefault('action', '');
  $dbQuerySet = DatabaseQuerySet::instance();

  $adminRedirect = function () use($urlQuery) {
    if ($urlQuery->getDefault('page', 'index') === 'admin') return;
    $urlQuery->set('page', 'admin')->redirect();
    exit();
  };

  $getGenreList = function (DataContainer $query) {
    $prfx = 'genre-';
    $prfxlen = strlen($prfx);

    return array_map(
      function (string $key) use($prfxlen) {
        return substr($key, $prfxlen);
      },

      array_keys(array_filter(
        $query->getData(),
        function (string $value, string $key) use($prfx) {
          return $value === 'on' && preg_match("/^$prfx/", $key);
        },
        ARRAY_FILTER_USE_BOTH
      ))
    );
  };

  switch ($action) {
    case 'check-admin-auth':
      $param->get('login-double-checker')->verify();
      return '
        <strong>Authenticated</strong>
      ';

    case 'add-game':
      $adminRedirect();
      $id = $postData->getDefault('id', '');
      $name = $postData->getDefault('name', '');
      $description = $postData->getDefault('description', '');
      $swf = $files->getFileNullable('swf', null);
      $img = $files->getFileNullable('img', null);

      $required = [
        'id' => $id,
        'name' => $name,
        'description' => $description,
        'swf' => $swf,
        'img' => $img,
      ];

      foreach ($required as $key => $value) {
        if (!$value) {
          http_response_code(400);
          die("
            Field <code>$key</code> is missing
          ");
        }
      }

      $param->get('game-manager')->add(array_merge($required, [
        'genre' => $getGenreList($postData),
      ]));

      $urlQuery->without([
        'action',
        'fullname',
        'previous-page',
      ])->assign([
        'type' => 'html',
        'subpage' => 'games',
      ])->redirect();
      break;

      case 'add-genre':
        $adminRedirect();
        $genreId = $urlQuery->getDefault('genre-id', '');
        $genreName = $urlQuery->getDefault('game-genre', '');
        $param->get('genre-manager')->add($genreId, $genreName);

        $urlQuery->without([
          'action',
          'genre-id',
          'game-genre',
        ])->assign([
          'type' => 'html',
          'page' => 'admin',
          'subpage' => 'games',
        ])->redirect();
        break;

    case 'edit-user':
      $adminRedirect();
      $username = $urlQuery->getDefault('username', '');
      $fullname = $urlQuery->getDefault('fullname', '');
      if (!$username || !$fullname) return ErrorPage::status(400)->render();
      $param->get('user-manager')->update($username, $fullname);

      $urlQuery->without([
        'action',
        'fullname',
        'previous-page',
      ])->assign([
        'type' => 'html',
        'subpage' => $urlQuery->get('previous-page'),
      ])->redirect();
      break;

    case 'edit-game':
      $adminRedirect();
      $prevId = $urlQuery->getDefault('game', '');
      $id = $postData->getDefault('id', '');
      $name = $postData->getDefault('name', '');
      $genre = $postData->getDefault('genre', '');
      $description = $postData->getDefault('description', '');
      $swf = $files->getFileNullable('swf', null);
      $img = $files->getFileNullable('img', null);

      $required = [
        'id' => $id,
        'name' => $name,
        'description' => $description,
      ];

      foreach ($required as $key => $value) {
        if (!$value) {
          http_response_code(400);
          die("
            Field <code>$key</code> is missing
          ");
        }
      }

      $param->get('game-manager')->update($prevId, array_merge($required, [
        'genre' => $getGenreList($postData),
        'swf' => $swf,
        'img' => $img,
      ]));

      $urlQuery->without([
        'action',
        'fullname',
        'previous-page',
        'game',
      ])->assign([
        'type' => 'html',
        'subpage' => 'games',
      ])->redirect();
      break;

    case 'edit-genre':
      $adminRedirect();
      $prevId = $urlQuery->getDefault('genre', '');
      $id = $postData->getDefault('id', '');
      $name = $postData->getDefault('name', '');

      if (!$prevId) {
        throw new NotFoundException("Field 'genre' is missing from url");
      }

      $required = [
        'id' => $id,
        'name' => $name,
      ];

      foreach ($required as $key => $value) {
        if (!$value) {
          http_response_code(400);
          die("
            Field <code>$key</code> is missing
          ");
        }
      }

      $param->get('genre-manager')->update($prevId, $required);

      $urlQuery->without([
        'type',
        'action',
        'genre',
      ])->assign([
        'page' => 'admin',
        'subpage' => 'games',
      ])->redirect();
      break;

    case 'delete-user':
      $adminRedirect();
      $username = $urlQuery->getDefault('username', '');
      $param->get('user-manager')->delete($username);

      $urlQuery->without([
        'action',
        'username',
      ])->assign([
        'type' => 'html',
        'page' => 'admin',
        'subpage' => 'users',
      ])->redirect();
      break;

    case 'delete-game':
      $adminRedirect();
      $game = $urlQuery->getDefault('game', '');
      $param->get('game-manager')->delete($game);

      $urlQuery->without([
        'action',
        'game',
      ])->assign([
        'type' => 'html',
        'page' => 'admin',
        'subpage' => 'games',
      ])->redirect();
      break;

    case 'delete-genre':
      $adminRedirect();
      $genre = $urlQuery->getDefault('genre', '');
      $param->get('genre-manager')->delete($genre);

      $urlQuery->without([
        'action',
        'genre',
      ])->assign([
        'type' => 'html',
        'page' => 'admin',
        'subpage' => 'games',
      ])->redirect();
      break;

    case 'update-admin-password':
      $adminRedirect();
      $currentPassword = $postData->getDefault('current-password', '');
      $newPassword = $postData->getDefault('new-password', '');
      $rePassword = $postData->getDefault('re-password', '');

      $loginDoubleChecker = $param->get('login-double-checker');
      $login = $loginDoubleChecker->get('login');
      $loginDoubleChecker->set(
        'login',
        $login->set('password', $currentPassword)
      )->verify();

      if ($newPassword !== $rePassword) throw SecurityException::permission();

      $param
        ->get('admin-manager')
        ->updatePassword($login->username(), $newPassword)
      ;

      $param
        ->get('cookie')
        ->set('admin-password', $newPassword)
        ->update()
      ;

      $urlQuery->assign([
        'type' => 'html',
        'page' => 'admin',
        'subpage' => 'advanced',
      ])->redirect();
      break;

    case 'reset-database':
      $adminRedirect();
      $urlQuery = $param->get('url-query');
      $password = $postData->getDefault('password', '');

      if ($postData->getDefault('confirmed', 'off') === 'on') {
        $loginDoubleChecker = $param->get('login-double-checker');
        $loginDoubleChecker->set(
          'login',
          $loginDoubleChecker
            ->get('login')
            ->set('password', $password)
        )->verify();

        $subaction = $urlQuery->getDefault('subaction', '');

        if ($subaction !== 'clear' && $subaction !== 'reset') {
          throw new NotFoundException();
        }

        $check = function (string $key) use($urlQuery) {
          return $urlQuery->getDefault($key, 'off') === 'on';
        };

        if ($check('game')) {
          $param->get('genre-manager')->$subaction();
          $param->get('game-manager')->$subaction();
        }

        if ($check('user')) {
          $param->get('user-manager')->$subaction();
        }

        if ($check('admin')) {
          $param->get('admin-manager')->$subaction();
        }

        if ($check('history')) {
          $param->get('history-manager')->$subaction();
        }

        if ($check('favourite')) {
          $param->get('favourite-manager')->$subaction();
        }

        if ($check('comment')) {
          $param->get('comment-manager')->$subaction();
        }
      }

      $urlQuery->except('action')->assign([
        'type' => 'html',
        'page' => 'admin',
        'subpage' => 'advanced',
      ])->redirect();
      break;

    case 'update-user-profile':
      $fullname = $postData->getDefault('fullname', '');
      $userprofile = $param->get('user-profile');

      if (!$fullname) {
        http_response_code(400);
        die('
          Field <code>fullname</code> is missing
        ');
      }

      $userprofile->update(['fullname' => $fullname]);

      $urlQuery->without([
        'type',
        'action',
      ])->set('page', 'profile')->redirect();
      break;

    case 'update-user-password':
      $currentPassword = $postData->getDefault('current-password', '');
      $newPassword = $postData->getDefault('new-password', '');
      $rePassword = $postData->getDefault('re-password', '');
      $userProfile = $param->get('user-profile');

      $userProfile->set(
        'login',
        $login->set('password', $currentPassword)
      )->verify();

      if ($newPassword !== $rePassword) throw SecurityException::permission();
      $userProfile->updatePassword($newPassword);

      $param
        ->get('cookie')
        ->set('password', $newPassword)
        ->update()
      ;

      $urlQuery->without([
        'type',
        'action',
      ])->set('page', 'profile')->redirect();
      break;

    case 'clear-user-history':
      $param->get('user-profile')->clearHistory();

      $urlQuery->without([
        'type',
        'action',
      ])->set('page', 'history')->redirect();
      break;

    default:
      throw new NotFoundException();
  }
}

function recordHistory(DataContainer $param): void {
  $urlQuery = $param->get('url-query');

  if ($urlQuery->getDefault('type', 'html') !== 'html') return;
  if ($urlQuery->getDefault('page', 'index') !== 'play') return;
  if (!$param->get('login')->isLoggedIn()) return;

  $game = $urlQuery->getDefault('game-id', '');
  $param->get('user-profile')->addHistory($game);
}

function main(): string {
  $constants = Constants::instance();
  $urlQuery = new UrlQuery($_GET);
  $postData = new HttpData($_POST);
  $files = UploadedFileSet::instance();
  $predefinedGames = PredefinedGames::create();
  $predefinedGenres = PredefinedGenres::create();
  $page = $urlQuery->getDefault('page', 'index');

  $cookie = Cookie::instance([
    'expiry-extend' => $constants->get('month'),
  ]);

  if ($urlQuery->hasKey('theme')) {
    $cookie->set('theme', $urlQuery->get('theme'))->update();
    $urlQuery->except('theme')->redirect();
  }

  $themeColorSet = getThemeColorSet($cookie);

  if ($themeColorSet['invalid']) {
    $themeColorSet['new-cookie']->update();
    $urlQuery->except('theme')->redirect();
  }

  $session = Session::instance();
  $sizeSet = SizeSet::instance();
  $imageSet = ImageSet::instance($themeColorSet);
  $dbQuerySet = DatabaseQuerySet::instance();

  $accountParams = [
    'is-admin' => $page === 'admin',
    'session' => $session,
    'post-data' => $postData,
    'cookie' => $cookie,
    'db-query-set' => $dbQuerySet,
    'url-query' => $urlQuery,
  ];

  $signup = SignUp::instance($accountParams)->verify();
  $login = Login::instance($accountParams)->verify();
  $logout = Logout::instance($accountParams);

  $securitySharedParam = ([
    'cookie' => $cookie,
    'session' => $session,
    'db-query-set' => $dbQuerySet,
    'login' => $login,
  ]);

  $loginDoubleChecker = new LoginDoubleChecker($securitySharedParam);
  $gameGenreRelationshipManager = new GameGenreRelationshipManager($securitySharedParam);
  $gameManager = new GameManager($securitySharedParam);
  $genreManager = new GenreManager($securitySharedParam);
  $userManager = new UserManager($securitySharedParam);
  $adminManager = new AdminManager($securitySharedParam);
  $historyManager = new HistoryManager($securitySharedParam);
  $favouriteManager = new FavouriteManager($securitySharedParam);
  $commentManager = new CommentManager($securitySharedParam);
  $userProfile = new UserProfile($securitySharedParam);
  $searchEngine = new SearchEngine($securitySharedParam);

  $param = RawDataContainer::instance([
    'title' => 'b6fb',
    'url-query' => $urlQuery,
    'post-data' => $postData,
    'files' => $files,
    'constants' => $constants,
    'predefined-games' => $predefinedGames,
    'predefined-genres' => $predefinedGenres,
    'theme-name' => $themeColorSet['name'],
    'colors' => $themeColorSet['colors'],
    'images' => $imageSet->getData(),
    'size-set' => $sizeSet,
    'sizes' => $sizeSet->getData(),
    'page' => $page,
    'session' => $session,
    'cookie' => $cookie,
    'subpages' => createSubpageList($login),
    'admin-page' => $urlQuery->getDefault('subpage', 'dashboard'),
    'admin-subpages' => createAdminSubpageList(),
    'db-query-set' => $dbQuerySet,
    'login-double-checker' => $loginDoubleChecker,
    'game-genre-relationship-manager' => $gameGenreRelationshipManager,
    'game-manager' => $gameManager,
    'genre-manager' => $genreManager,
    'user-manager' => $userManager,
    'admin-manager' => $adminManager,
    'history-manager' => $historyManager,
    'favourite-manager' => $favouriteManager,
    'comment-manager' => $commentManager,
    'user-profile' => $userProfile,
    'search-engine' => $searchEngine,
    'signup' => $signup,
    'login' => $login,
    'logout' => $logout,
  ]);

  recordHistory($param);

  try {
    switch ($urlQuery->getDefault('type', 'html')) {
      case 'html':
        return sendHtml($param);
      case 'file':
        return sendFile($urlQuery);
      case 'api':
        $api = ApplicationProgrammingInterface::instance($param);
        return sendAjax($api);
      case 'action':
        return sendAction($param);
      default:
        throw new NotFoundException();
    }
  } catch (NotFoundException $err) {
    return ErrorPage::status(404, $err)->render();
  } catch (SecurityException $err) {
    return ErrorPage::status(401, $err)->render();
  }
}
?>
