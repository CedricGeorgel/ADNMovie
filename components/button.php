<?php
/**
 * BUTTON.PHP - Composant bouton universel
 * @param string $label Texte du bouton
 * @param string $type 'button' ou 'link'
 * @param string $class Classes CSS additionnelles (ex: 'active', 'btn-danger')
 * @param array $attrs Attributs HTML (ex: ['onclick' => '...', 'href' => '...'])
 */
function renderButton($label, $type = 'button', $class = '', $attrs = []) {
    $attributes = '';
    foreach ($attrs as $key => $val) {
        $attributes .= ' ' . h($key) . '="' . h($val) . '"';
    }

    if ($type === 'link') {
        echo '<a class="btn-base ' . h($class) . '" ' . $attributes . '>' . h($label) . '</a>';
    } else {
        echo '<button class="btn-base ' . h($class) . '" ' . $attributes . '>' . h($label) . '</button>';
    }
}