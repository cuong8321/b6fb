<?php
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/meta-element.php';
require_once __DIR__ . '/text-area-element.php';
require_once __DIR__ . '/css-view.php';
require_once __DIR__ . '/logo.php';
require_once __DIR__ . '/anchor.php';
require_once __DIR__ . '/header-section.php';
require_once __DIR__ . '/sidebar-navigator.php';
require_once __DIR__ . '/hidden-input.php';
require_once __DIR__ . '/labeled-input.php';
require_once __DIR__ . '/markdown-view.php';
require_once __DIR__ . '/../../lib/utils.php';

class AdminUserInterface extends RawDataContainer implements Component {
  public function render(): Component {
    $urlQuery = $this->get('url-query');
    $login = $this->get('login');
    $username = $login->username();
    $isLoggedIn = $login->isLoggedIn();
    $cssFileName = $isLoggedIn ? 'admin' : 'login';
    $images = $this->get('images');

    return HtmlElement::create('html', [
      'lang' => 'en',
      'dataset' => [
        'username' => $username,
      ],
      'classes' => [
        'admin',
        $isLoggedIn ? 'logged-in' : 'anonymous',
      ],
      HtmlElement::create('head', [
        new CharsetMetaElement('utf-8'),
        HtmlElement::create('title', 'Quản trị'),
        CssView::fromFile(__DIR__ . "/../../resources/styles/$cssFileName.css"),
      ]),
      $isLoggedIn
        ? HtmlElement::create('body', [
          HtmlElement::emmetBottom('header#main-header', [
            HtmlElement::emmetTop('a#title-header', [
              'style' => [
                'padding-left' => '10px',
              ],
              'href' => '?page=admin',
              'Quản trị',
            ]),
            HtmlElement::emmetBottom('#username-admin', [
              $login->username(),
            ]),
            HtmlElement::emmetBottom('#logo-admin>button#profile-button', [
              HtmlElement::emmetTop('img#popup-profile-image', [
                'src' => $images['default-avatar-white-image'],
              ]),
            ]),
            HtmlElement::emmetTop('#profile-setting', [
              'hidden' => true,
              HtmlElement::emmetBottom('#setting-admin>a', [
                'href' => $urlQuery->set('subpage', 'change-admin-password')->getUrlQuery(),
                'Cài đặt',
              ]),
              HtmlElement::emmetBottom('#logout-admin>a', [
                'href' => $urlQuery->set('subpage', 'logout')->getUrlQuery(),
                'Đăng xuất',
              ]),
            ]),
          ]),
          HtmlElement::create('main', [
            new AdminNavigatorSection($this->getData()),
            new AdminMainSection($this->getData()),
          ]),
          JavascriptEmbed::file(__DIR__ . '/../../resources/scripts/lib.js'),
          JavascriptEmbed::file(__DIR__ . '/../../resources/scripts/script.js'),
        ])
        : HtmlElement::emmetBottom('body#login-page>#page.aligner', [
          HtmlElement::emmetTop('.top-aligned.aligned-item', []),
          HtmlElement::emmetTop('.middle-aligned.aligned-item', [
            HtmlElement::create('header', Logo::instance($this->getData())),
            HtmlElement::emmetBottom('section#main-section', [
              HtmlElement::emmetTop('h1#login-title', [
                HtmlElement::emmetTop('span.login-title', 'Đăng nhập'),
                HtmlElement::emmetTop('span.login-subtitle', '(Admin)'),
              ]),
              HtmlElement::create('main', [
                new LoginForm([
                  'action' => $urlQuery->getUrlQuery(),
                  'hidden-values' => [
                    'logged-in' => 'on',
                  ],
                ]),
              ]),
            ]),
          ]),
          HtmlElement::emmetTop('.bottom-aligned.aligned-item', []),
        ]),
    ]);
  }
}

class AdminNavigatorSection extends RawDataContainer implements Component {
  public function render(): Component {
    $currentpage = $this->get('admin-page');
    $subpagetmpls = $this->get('admin-subpages');

    return new SidebarNavigator(
      [[[], $subpagetmpls]],
      function ($tmpl) {
        return [
          'href' => $tmpl['href'],
          $tmpl['title']
        ];
      },
      function ($tmpl) use($currentpage) {
        return $tmpl['subpage'] === $currentpage;
      }
    );
  }
}

class AdminMainSection extends RawDataContainer implements Component {
  public function render(): Component {
    $data = $this->getData();
    $page = $data['admin-page'];

    switch ($page) {
      case 'logout':
        $this->get('logout')->set('prefix', 'admin-')->act();
        $this->get('url-query')->except('subpage')->redirect();
        return new TextNode('Logged Out');
      case 'dashboard':
        return new AdminDashboard($data);
      case 'games':
        return new AdminGames($data);
      case 'users':
        return new AdminUsers($data);
      case 'advanced':
        return new AdminAdvanced($data);
      case 'edit-user':
        return new AdminEditUser($data);
      case 'edit-genre':
        return new AdminEditGenre($data);
      case 'edit-game':
        return new AdminEditGame($data);
      case 'add-game':
        return new AdminAddGame($data);
      case 'add-genre':
        return new AdminAddGenre($data);
      case 'delete-user':
        return new AdminDeleteUser($data);
      case 'delete-genre':
        return new AdminDeleteGenre($data);
      case 'delete-game':
        return new AdminDeleteGame($data);
      case 'reset-database':
        return new AdminResetDatabase($data);
      case 'change-admin-password':
        return new AdminChangePassword($data);
      default:
        throw new NotFoundException();
    }
  }
}

class AdminDashboard extends RawDataContainer implements Component {
  public function render(): Component {
    $images = $this->get('images');
    $gameManager = $this->get('game-manager');
    $userManager = $this->get('user-manager');

    return HtmlElement::emmetBottom('#dashboard', [
      HtmlElement::emmetTop('.header-subpage', [
        HtmlElement::create('h1', 'Bảng điều khiển'),
      ]),
      HtmlElement::emmetTop('.body-subpage', [
        DashboardPanel::create($this, 'games', 'gamepad-image', 'Trò chơi', $gameManager->count()),
        DashboardPanel::create($this, 'users', 'multi-users-image', 'Người dùng', $userManager->count()),
      ]),
    ]);
  }
}

class DashboardPanel extends RawDataContainer implements Component {
  public function render(): Component {
    $id = $this->getDefault('id', '');
    $img = $this->get('images')[$this->getDefault('img', '')];
    $subtitle = $this->get('subtitle');
    $count = $this->getDefault('count', 0);
    $urlQuery = $this->get('url-query');

    return HtmlElement::create('a', [
      'id' => "dashboard-$id",
      'href' => $urlQuery->set('subpage', $id)->getUrlQuery(),
      HtmlElement::emmetBottom('.image-container>img', [
        'src' => $img,
      ]),
      HtmlElement::emmetTop('.list', [
        HtmlElement::emmetTop('.count', [$count . ' ']),
        HtmlElement::emmetTop('.subtitle', $subtitle),
      ]),
    ]);
  }

  static public function create(RawDataContainer $base, string $id, string $img, string $subtitle, int $count): self {
    return new static(array_merge($base->getData(), [
      'id' => $id,
      'img' => $img,
      'subtitle' => $subtitle,
      'count' => $count,
    ]));
  }
}

class AdminGames extends RawDataContainer implements Component {
  public function render(): Component {
    $urlQuery = $this->get('url-query')->assign([
      'page' => 'admin',
      'previous-page' => 'games',
    ]);

    $games = $this->get('game-manager')->list();
    $genres = $this->get('genre-manager')->list();

    $listgame = array_map(
      function (array $userinfo) use($urlQuery) {
        [
          'id' => $id,
          'name' => $name,
          'genre' => $genre,
        ] = $userinfo;

        return HtmlElement::create('tr', [
          HtmlElement::create('td', $id),
          HtmlElement::create('td', $name),
          HtmlElement::create('td', implode(', ', array_values($genre))),
          HtmlElement::create('td', new AdminEditDeletePair(
            $urlQuery->set('game', $id),
            'edit-game',
            'delete-game'
          )),
        ]);
      },
      $games
    );

    $listgenres = array_map(
      function (array $genreinfo) use($urlQuery) {
        [
          'id' => $id,
          'name' => $name,
        ] = $genreinfo;

        return HtmlElement::create('tr', [
          HtmlElement::create('td', $id),
          HtmlElement::create('td', $name),
          HtmlElement::create('td', new AdminEditDeletePair(
            $urlQuery->set('genre', $id),
            'edit-genre',
            'delete-genre'
          )),
        ]);
      },
      $genres
    );

    return HtmlElement::emmetTop('#list-games', [
      HtmlElement::emmetTop('.header-subpage', [
        HtmlElement::create('h1', 'Quản lý Trò chơi'),
        HtmlElement::emmetTop('button.btn-add#btn-add-genre', [
          HtmlElement::emmetTop('a', [
            'href' => $urlQuery->set('subpage', 'add-genre')->getUrlQuery(),
            'Thêm thể loại',
          ]),
        ]),
        HtmlElement::emmetTop('button.btn-add#btn-add-game', [
          HtmlElement::emmetTop('a', [
            'href' => $urlQuery->set('subpage', 'add-game')->getUrlQuery(),
            'Thêm trò chơi',
          ]),
        ]),
      ]),
      HtmlElement::emmetTop('.body-subpage', [
        HtmlElement::emmetTop('article.genre-list-container', [
          HtmlElement::create('h2', 'Danh sách Thể loại'),
          HtmlElement::emmetTop('table#genre-list', [
            HtmlElement::emmetBottom('thead>tr.class-tr-genres', [
            HtmlElement::create('th', ['ID']),
            HtmlElement::create('th', ['Tên']),
            HtmlElement::create('th', ['Điều khiển']),
          ]),
            HtmlElement::create('tbody', $listgenres),
          ]),
        ]),
        HtmlElement::emmetTop('article.game-list-container', [
          HtmlElement::create('h2', 'Danh sách Trò chơi'),
          HtmlElement::emmetTop('table#game-list', [
            HtmlElement::emmetBottom('thead>tr.class-tr-games', [
            HtmlElement::create('th', ['ID']),
            HtmlElement::create('th', ['Tên']),
            HtmlElement::create('th', ['Thể loại']),
            HtmlElement::create('th', ['Điều khiển']),
          ]),
            HtmlElement::create('tbody', $listgame),
          ]),
        ]),
      ]),
    ]);
  }
}

class AdminDeleteGenre extends RawDataContainer implements Component {
  public function render(): Component {
    $urlQuery = $this->get('url-query');
    $genre = $urlQuery->get('genre');
    $genreInfo = $this->get('genre-manager')->info($genre);

    if (!$genreInfo) throw new NotFoundException();
    [$genreName] = $genreInfo;

    return new AdminDeleteConfirmBox(
      $this->assign([
        'url-query' => $urlQuery->set('genre', $genre),
        'title' => 'Xóa thể loại',
        'warning' => "Thao tác sau đây sẽ xóa thể loại _“{$genreName}”_. Hành động này **không thể hoàn tác**.",
        'question' => HtmlElement::emmetTop('.question', [
          'Bạn có thực sự muốn xóa vĩnh viễn thể loại',
          HtmlElement::emmetTop('em.target.name', "“{$genreName}”"),
          ' không?',
        ]),
        'delete-action' => 'delete-genre',
        'back-subpage' => 'games',
      ])->getData()
    );
  }
}

class AdminDeleteGame extends RawDataContainer implements Component {
  public function render(): Component {
    $urlQuery = $this->get('url-query');
    $game = $urlQuery->get('game');
    $gameInfo = $this->get('game-manager')->info($game);

    if (!$gameInfo) throw new NotFoundException();
    [$gameName] = $gameInfo;

    return new AdminDeleteConfirmBox(
      $this->assign([
        'url-query' => $urlQuery->assign([
          'type' => 'action',
          'previous-page' => 'games',
          'game' => $game,
        ]),
        'title' => 'Xóa trò chơi',
        'warning' => "Thao tác sau đây sẽ xóa trò chơi _“{$gameName}”_. Hành động này **không thể hoàn tác**.",
        'question' => HtmlElement::emmetTop('.question', [
          'Bạn có thực sự muốn xóa vĩnh viễn trò chơi',
          HtmlElement::emmetTop('em.target.name', "“{$gameName}”"),
          ' không?',
        ]),
        'delete-action' => 'delete-game',
        'back-subpage' => 'games',
      ])->getData()
    );
  }
}

class AdminEditGenre extends RawDataContainer implements Component {
  public function render(): Component {
    $urlQuery = $this->get('url-query');
    $genre = $urlQuery->get('genre');
    $genreName = $this->get('genre-manager')->info($genre)['name'];

    return HtmlElement::emmetBottom('.editing-page', [
      HtmlElement::emmetTop('.header-subpage', []),
      HtmlElement::emmetTop('.body-subpage', [
        HtmlElement::emmetBottom('form.editing-form', [
          'method' => 'POST',
          'action' => $urlQuery->assign([
            'type' => 'action',
            'action' => 'edit-genre',
            'previous-page' => 'games',
            'id' => $genre,
          ])->getUrlQuery(),
          HtmlElement::emmetTop('.input-container',[
            HtmlElement::emmetBottom('legend>h2', 'Cập nhật thể loại'),
            PlainLabeledInput::text('id', 'ID', $genre),
            PlainLabeledInput::text('name', 'Tên thể loại', $genreName),
          ]),
          new AdminEditingSaveButton(),
        ]),
      ]),
    ]);
  }
}

class AdminEditGame extends RawDataContainer implements Component {
  public function render(): Component {
    $urlQuery = $this->get('url-query');
    $id = $urlQuery->getDefault('game', '');
    $info = $this->get('game-manager')->info($id);

    if (!$id || !$info) {
      throw new NotFoundException();
    }

    return HtmlElement::emmetTop('#edit-game-page', [
      HtmlElement::emmetTop('.header-subpage', [
        HtmlElement::create('h2','Sửa trò chơi'),
      ]),
      HtmlElement::emmetBottom('.body-subpage-game>form#add-game-form.add', [
        'method' => 'POST',
        'action' => $urlQuery->assign([
          'type' => 'action',
          'action' => 'edit-game',
        ])->getUrlQuery(),
        'enctype' => 'multipart/form-data',
        HtmlElement::emmetTop('.input-container', [
          PlainLabeledInput::text('id', 'ID', $id),
          PlainLabeledInput::text('name', 'Tên trò chơi', $info['name']),
          HtmlElement::create('p', 'Thể loại'),
          new GenreCheckboxSet(
            $this
              ->set('checked', array_keys($info['genre']))
              ->getData()
          ),
          new TextAreaElement([
            'name' => 'description',
            'required' => true,
            $info['description'],
          ]),
          LabeledFileInput::text('swf', 'Tệp trò chơi (.swf)'),
          LabeledFileInput::text('img', 'Tệp hình ảnh (.jpg)'),
        ]),
        new AdminSubmitResetPair(),
      ]),
    ]);
  }
}

class AdminAddGame extends RawDataContainer implements Component {
  public function render(): Component {
    $urlQuery = $this->get('url-query');

    return HtmlElement::emmetTop('#edit-game-page', [
      HtmlElement::emmetTop('.header-subpage', [
        HtmlElement::create('h2','Thêm trò chơi'),
      ]),
      HtmlElement::emmetBottom('.body-subpage-game>form.add', [
        'method' => 'POST',
        'action' => $urlQuery->assign([
          'type' => 'action',
          'action' => 'add-game',
        ])->getUrlQuery(),
        'enctype' => 'multipart/form-data',
        HtmlElement::emmetTop('.input-container', [
          PlainLabeledInput::text('id', 'ID'),
          PlainLabeledInput::text('name', 'Tên trò chơi'),
          HtmlElement::create('p', 'Thể loại'),
          new GenreCheckboxSet($this->getData()),
          RequiredTextArea::text('description', 'Mô tả'),
          RequiredFileInput::text('swf', 'Tệp trò chơi (.swf)'),
          RequiredFileInput::text('img', 'Tệp hình ảnh (.jpg)'),
        ]),
        new AdminSubmitResetPair(),
      ]),
    ]);
  }
}

class AdminAddGenre extends RawDataContainer implements Component {
  public function render(): Component {
    $urlQuery = $this->get('url-query');

    return HtmlElement::emmetTop('#edit-genre-page', [
      HtmlElement::emmetTop('.header-subpage', [
        HtmlElement::create('h2','Thêm Thể loại'),
      ]),
      HtmlElement::emmetBottom('.body-subpage>form#add-genre-form.add', [
        'method' => 'GET',
        'action' => '.',
        HtmlElement::emmetTop('.input-container', [
          PlainLabeledInput::text('genre-id', 'ID'),
          PlainLabeledInput::text('game-genre', 'Tên thể loại'),
        ]),
        new AdminSubmitResetPair(),
        new HiddenInputSet($urlQuery->assign([
          'type' => 'action',
          'action' => 'add-genre',
        ])->getData()),
      ]),
    ]);
  }
}

class AdminUsers extends RawDataContainer implements Component {
  public function render(): Component {
    $users = $this->get('user-manager')->list();
    $urlQuery = $this->get('url-query')->assign([
      'page' => 'admin',
      'previous-page' => 'users',
    ]);

    $children = array_map(
      function (array $userinfo) use($urlQuery) {
        [$username, $fullname] = $userinfo;
        return HtmlElement::create('tr', [
          HtmlElement::create('td', $username),
          HtmlElement::create('td', $fullname),
          HtmlElement::create('td', new AdminEditDeletePair(
            $urlQuery->set('username', $username),
            'edit-user',
            'delete-user'
          )),
        ]);
      },
      $users
    );

    return HtmlElement::emmetTop('#user-account', [
      HtmlElement::emmetTop('#header-user-page.header-subpage', [
        HtmlElement::create('h1', 'Tài khoản người dùng'),
      ]),
      HtmlElement::emmetTop('.body-subpage', [
        HtmlElement::emmetTop('table#tb-games', [
          HtmlElement::emmetBottom('thead>tr.class-tr-games', [
            HtmlElement::create('th', ['Tên người dùng']),
            HtmlElement::create('th', ['Tên đầy đủ']),
            HtmlElement::create('th', ['Điều khiển']),
          ]),
          HtmlElement::create('tbody', $children),
        ]),
      ]),
    ]);
  }
}

class AdminAdvanced extends RawDataContainer implements Component {
  public function render(): Component {
    $data = $this->getData();

    return HtmlElement::emmetBottom('.content', [
      HtmlElement::emmetBottom('.header-subpage>h1', 'Nâng cao'),
      new AdminAdvancedAdminManagementSection($data),
      new AdminAdvancedResetDatabaseSection($data),
    ]);
  }
}

class AdminAdvancedResetDatabaseSection extends RawDataContainer implements Component {
  public function render(): Component {
    $urlQuery = $this->get('url-query');

    return HtmlElement::emmetTop('article', [
      HtmlElement::create('h2', 'Reset và Khởi tạo'),
      HtmlElement::create('form', [
        'method' => 'GET',
        'action' => '.',
        HtmlElement::emmetTop('.input-container', [
          LabeledCheckbox::text('game', 'Dữ liệu Trò chơi'),
          LabeledCheckbox::text('user', 'Dữ liệu Người dùng'),
          LabeledCheckbox::text('admin', 'Dữ liệu Người quản trị'),
          LabeledCheckbox::text('history', 'Lịch sử Truy cập Trò chơi'),
          LabeledCheckbox::text('favourite', 'Danh sách Trò chơi được Yêu thích'),
          LabeledCheckbox::text('comment', 'Bình luận'),
        ]),
        HtmlElement::emmetTop('.button-container', [
          HtmlElement::create('button', [
            'name' => 'subaction',
            'type' => 'submmit',
            'value' => 'clear',
            'Làm trống CSDL',
          ]),
          HtmlElement::create('button', [
            'name' => 'subaction',
            'type' => 'submmit',
            'value' => 'reset',
            'Đặt lại CSDL',
          ])
        ]),
        new HiddenInputSet(
          $urlQuery
            ->set('subpage', 'reset-database')
            ->getData()
        ),
      ]),
    ]);
  }
}

class AdminAdvancedAdminManagementSection extends RawDataContainer implements Component {
  public function render(): Component {
    $urlQuery = $this->get('url-query');

    return HtmlElement::emmetTop('article', [
      HtmlElement::create('h2', 'Quản lý Tài khoản Quản trị'),
      HtmlElement::create('div', [
        HtmlElement::create('ul', [
          HtmlElement::emmetBottom('li>a', [
            'href' => $urlQuery->set('subpage', 'change-admin-password')->getUrlQuery(),
            'Đổi mật khẩu',
          ]),
        ]),
      ]),
    ]);
  }
}

class AdminChangePassword extends RawDataContainer implements Component {
  public function render(): Component {
    $urlQuery = $this->get('url-query');

    return  HtmlElement::emmetTop('#update-password-account', [
      HtmlElement::emmetTop('#header-user-page.header-subpage', [
        HtmlElement::create('h1', 'Thay đổi mật khẩu'),
      ]),
      HtmlElement::emmetBottom('.body-subpage>form.change', [
        'method' => 'POST',
        'action' => $urlQuery->assign([
          'type' => 'action',
          'action' => 'update-admin-password',
        ])->getUrlQuery(),
        HtmlElement::emmetTop('.input-container', [
          SecretLabeledInput::text('current-password', 'Mật khẩu hiện tại'),
          SecretLabeledInput::text('new-password', 'Mật khẩu mới'),
          SecretLabeledInput::text('re-password', 'Nhập lại Mật khẩu mới'),
        ]),
        HtmlElement::emmetTop('.button-container', [
          new AdminSubmitResetPair(),
        ]),
      ]),
    ]);
  }
}

class AdminEditUser extends RawDataContainer implements Component {
  public function render(): Component {
    $urlQuery = $this->get('url-query');
    $username = $urlQuery->get('username');
    $fullname = $this->get('user-manager')->getUserFullname($username);

    return HtmlElement::emmetBottom('.editing-page', [
      HtmlElement::emmetTop('.header-subpage', []),
      HtmlElement::emmetTop('.body-subpage', [
        HtmlElement::emmetBottom('form.editing-form', [
          'method' => 'GET',
          'action' => '.',
          HtmlElement::emmetTop('.input-container', [
            HtmlElement::emmetBottom('legend>h2', 'Cập nhật người dùng'),
            HtmlElement::emmetTop('.form-group', [
              HtmlElement::create('label', 'Tên người dùng'),
              HtmlElement::create('output', $username),
            ]),
            PlainLabeledInput::text('fullname', 'Họ và Tên', $fullname),
          ]),
          new AdminEditingSaveButton(),
          HiddenInputSet::instance($urlQuery->assign([
            'type' => 'action',
            'action' => 'edit-user',
            'previous-page' => 'users',
            'username' => $username,
          ])->getData()),
        ]),
      ]),
    ]);
  }
}

class AdminDeleteUser extends RawDataContainer implements Component {
  public function render(): Component {
    $urlQuery = $this->get('url-query');
    $username = $urlQuery->get('username');

    return new AdminDeleteConfirmBox(
      $this->assign([
        'url-query' => $urlQuery->set('username', $username),
        'title' => 'Xóa người dùng',
        'warning' => "Thao tác sau đây sẽ xóa người dùng _“{$username}”_. Hành động này **không thể hoàn tác**.",
        'question' => HtmlElement::emmetTop('.question', [
          'Bạn có thực sự muốn xóa vĩnh viễn người dùng',
          HtmlElement::emmetTop('em.target.name', "“{$username}”"),
          ' không?',
        ]),
        'delete-action' => 'delete-user',
        'back-subpage' => 'users',
      ])->getData()
    );
  }
}

class AdminResetDatabase extends RawDataContainer implements Component {
  public function render(): Component {
    $urlQuery = $this->get('url-query');

    $subaction = $urlQuery->getDefault('subaction', '');

    if ($subaction !== 'clear' && $subaction !== 'reset') {
      throw new NotFoundException();
    }

    $actionName = $subaction === 'reset' ? 'Đặt lại' : 'Làm trống';

    return HtmlElement::emmetTop('#reset-database', [
      HtmlElement::emmetBottom('.header-subpage>h1', "$actionName Cơ sở dữ liệu"),
      new AdminWarningBox('CSDL sẽ bị **xóa sạch**. Hành động này **không thể hoàn tác**.'),
      HtmlElement::emmetBottom('.question>strong>h3', 'Bạn có muốn tiếp tục?'),
      HtmlElement::emmetBottom('.answer>form', [
        'method' => 'POST',
        'action' => $urlQuery->assign([
          'type' => 'action',
          'action' => 'reset-database',
        ])->getUrlQuery(),
        HtmlElement::emmetTop('.input-container', [
          SecretLabeledInput::text('password', 'Mật khẩu Admin'),
        ]),
        HtmlElement::emmetTop('.button-container', [
          HtmlElement::emmetTop('button.confirm.dangerous', [
            'type' => 'confirm',
            'Xóa và Đặt lại CSDL',
          ]),
          HtmlElement::emmetBottom('button.cancel.safe>a', [
            'href' => $urlQuery->set('subpage', 'advanced')->getUrlQuery(),
            'Quay lại',
          ]),
        ]),
        new HiddenInputSet(
          $urlQuery->set('confirmed', 'on')->getData()
        ),
      ]),
    ]);
  }
}

class GenreCheckboxSet extends RawDataContainer implements Component {
  static protected function requiredFieldSchema(): array {
    return array_merge(parent::requiredFieldSchema(), [
      'genre-manager' => 'GenreManager',
    ]);
  }

  public function render(): Component {
    $all = $this->get('genre-manager')->list();
    $checked = $this->getDefault('checked', null);

    $isChecked = $checked
      ? function (string $id) use($checked) {
        return in_array($id, $checked);
      }
      : function () {
        return false;
      }
    ;

    return HtmlElement::create('div', array_map(
      function (array $item) use($isChecked) {
        [
          'id' => $id,
          'name' => $name,
        ] = $item;

        return LabeledCheckbox::text(
          "genre-$id",
          $name,
          $isChecked($id)
        );
      },
      $all
    ));
  }
}

class AdminDeleteConfirmBox extends RawDataContainer implements Component {
  static protected function requiredFieldSchema(): array {
    return [
      'url-query' => 'UrlQuery',
      'title' => '',
      'warning' => 'string',
      'question' => '',
      'delete-action' => 'string',
      'back-subpage' => 'string',
    ];
  }

  public function render(): Component {
    $urlQuery = $this->get('url-query');

    return HtmlElement::emmetTop('#delete-user-page', [
      HtmlElement::emmetBottom('.header-subpage>h1', $this->get('title')),
      new AdminWarningBox($this->get('warning')),
      HtmlElement::emmetBottom('.body-subpage', [
        HtmlElement::emmetTop('.question', $this->get('question')),
        HtmlElement::emmetBottom('.answer', [
          HtmlElement::emmetTop('.button-container', [
            HtmlElement::emmetBottom('button.dangerous>a#delete', [
              'href' => $urlQuery
                ->except('subpage')
                ->set('type', 'action')
                ->set('action', $this->get('delete-action'))
                ->getUrlQuery()
              ,
              'Xóa',
            ]),
            HtmlElement::emmetBottom('button.safe.cancel>a#cancel', [
              'href' => $urlQuery
                ->without(['action', 'previous-page', 'type'])
                ->set('subpage', $this->get('back-subpage'))
                ->getUrlQuery()
              ,
              'Quay lại',
            ]),
          ]),
        ]),
      ]),
    ]);
  }
}

class AdminEditDeletePair implements Component {
  private $urlQuery, $edit, $delete;

  public function __construct(UrlQuery $urlQuery, string $edit, string $delete) {
    $this->urlQuery = $urlQuery;
    $this->edit = $edit;
    $this->delete = $delete;
  }

  public function render(): Component {
    return HtmlElement::create('div', [
      new Anchor(
        $this->urlQuery->set('subpage', $this->edit)->getUrlQuery(),
        ['Sửa']
      ),
      new Anchor(
        $this->urlQuery->set('subpage', $this->delete)->getUrlQuery(),
        ['Xóa']
      ),
    ]);
  }
}

class AdminEditingSaveButton implements Component {
  public function render(): Component {
    return HtmlElement::emmetTop('.button-container', [
      HtmlElement::emmetTop('label.before-button', []),
      HtmlElement::emmetTop('button', [
        'type' => 'submit',
        'Lưu',
      ]),
    ]);
  }
}

class AdminSubmitResetPair implements Component {
  public function render(): Component {
    return HtmlElement::emmetTop('.button-container', [
      HtmlElement::emmetTop('button.submit', [
        'type' => 'submit',
        'name' => 'submit',
        'Lưu',
      ]),
      HtmlElement::emmetTop('button.reset', [
        'type' => 'reset',
        'name' => 'reset',
        'Đặt lại',
      ]),
    ]);
  }
}

class AdminWarningBox implements Component {
  private $content;

  public function __construct(string $content) {
    $this->content = $content;
  }

  public function render(): Component {
    return HtmlElement::emmetTop('.warning', [
      MarkdownView::indented("⚠ **Cảnh báo:** $this->content"),
    ]);
  }
}
?>
