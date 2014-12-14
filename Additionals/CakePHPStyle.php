<?php
class CakePHPStyle extends AdditionalPass {
	private $found_tokens;
	public function candidate($source, $found_tokens) {
		$this->found_tokens = $found_tokens;
		return true;
	}

	public function format($source) {
		$fmt = new PSR2ModifierVisibilityStaticOrder();
		if ($fmt->candidate($source, $this->found_tokens)) {
			$source = $fmt->format($source);
		}
		$source = $this->add_underscores_before_name($source);
		$source = $this->remove_space_after_casts($source);
		return $source;
	}

	private function remove_space_after_casts($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$max_detected_indent = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_VARIABLE:
					if (
						$this->left_useful_token_is([
							T_ARRAY_CAST,
							T_BOOL_CAST,
							T_DOUBLE_CAST,
							T_INT_CAST,
							T_OBJECT_CAST,
							T_STRING_CAST,
							T_UNSET_CAST,
						])
					) {
						$this->rtrim_and_append_code($text);
						break;
					}
				default:
					$this->append_code($text);
					break;
			}
		}
		return $this->code;
	}
	private function add_underscores_before_name($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		$max_detected_indent = 0;
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_PUBLIC:
				case T_PRIVATE:
				case T_PROTECTED:
					$level_touched = $id;
					$this->append_code($text);
					break;

				case T_VARIABLE:
					if ($this->left_useful_token_is([T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC])) {
						$text = str_replace('$_', '$', $text);
						$text = str_replace('$_', '$', $text);
						if (T_PROTECTED == $level_touched) {
							$text = str_replace('$', '$_', $text);
						} elseif (T_PRIVATE == $level_touched) {
							$text = str_replace('$', '$__', $text);
						}
					}
					$this->append_code($text);
					$level_touched = null;
					break;
				case T_STRING:
					if (
						$this->left_useful_token_is(T_FUNCTION) &&
						'__construct' != $text &&
						'__destruct' != $text &&
						'__call' != $text &&
						'__callStatic' != $text &&
						'__get' != $text &&
						'__set' != $text &&
						'__isset' != $text &&
						'__unset' != $text &&
						'__sleep' != $text &&
						'__wakeup' != $text &&
						'__toString' != $text &&
						'__invoke' != $text &&
						'__set_state' != $text &&
						'__clone' != $text &&
						' __debugInfo' != $text
					) {
						$text = str_replace('__', '', $text);
						$text = str_replace('_', '', $text);
						if (T_PROTECTED == $level_touched) {
							$text = '_' . $text;
						} elseif (T_PRIVATE == $level_touched) {
							$text = '__' . $text;
						}
					}
					$this->append_code($text);
					$level_touched = null;
					break;
				default:
					$this->append_code($text);
					break;
			}
		}

		return $this->code;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function get_description() {
		return 'Applies CakePHP Coding Style';
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function get_example() {
		return <<<'EOT'
<?php
namespace A;

class A {
	private $__a;
	protected $_b;
	public $c;

	public function b() {
		if($a) {
			noop();
		} else {
			noop();
		}
	}

	protected function _c() {
		if($a) {
			noop();
		} else {
			noop();
		}
	}
}
?>
EOT;
	}
}
