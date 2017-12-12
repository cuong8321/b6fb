<?php
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/../../lib/utils.php';

class ComboBox extends RawDataContainer implements Component {
  public function render(): Component {
    $default = $this->getDefault('value', false);
    $paramChildren = $this->getDefault('children', []);

    $children = array_values(array_map(
      function (string $value, string $key) use($default) {
        return new ComboBoxOption($key, $value, $key === $default);
      },
      array_values($paramChildren),
      array_keys($paramChildren)
    ));

    $attributes = $this->getDefault('attributes', []);

    return HtmlElement::create('select', array_merge($children, $attributes, [
      'value' => $default,
    ]));
  }

  static public function children(array $children, string $default = '', array $attributes = []): self {
    return new static([
      'children' => $children,
      'default' => $default,
      'attributes' => $attributes,
    ]);
  }
}

class LabeledComboBox extends ComboBox {
  public function render(): Component {
    $label = $this->getDefault('label', '');

    return HtmlElement::create('labeled-combo-box', [
      HtmlElement::create('label', $label),
      parent::render(),
    ]);
  }
}

class ComboBoxOption implements Component {
  private $value, $content, $selected;

  public function __construct(string $value, string $content, bool $selected) {
    $this->value = $value;
    $this->content = $content;
    $this->selected = $selected;
  }

  public function render(): Component {
    return HtmlElement::create('option', [
      'value' => $this->value,
      'selected' => $this->selected,
      $this->content,
    ]);
  }
}
?>
