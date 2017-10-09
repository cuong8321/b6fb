<?php
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/header-section.php';
require_once __DIR__ . '/main-section.php';
require_once __DIR__ . '/footer-section.php';

class Body implements Component {
  public function render(): Component {
    return HTMLElement::create('body', array(
      new HeaderSection(),
      new MainSection(),
      new FooterSection()
    ));
  }
}
?>
