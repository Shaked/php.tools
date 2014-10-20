<?php
final class AddMissingCurlyBraces extends FormatterPass {
	public function format($source) {
		$tmp = $this->addBraces($source);
		while (true) {
			$source = $this->addBraces($tmp);
			if ($source === $tmp) {
				break;
			}
			$tmp = $source;
		}
		return $source;
	}
	private function addBraces($source) {
		$this->tkns = token_get_all($source);
		$this->code = '';
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			switch ($id) {
				case T_FOREACH:
				case T_FOR:
					$this->append_code($text, false);
					$paren_count = null;
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;
						if (ST_PARENTHESES_OPEN === $id) {
							++$paren_count;
						} elseif (ST_PARENTHESES_CLOSE === $id) {
							$paren_count--;
						}
						$this->append_code($text, false);
						if (0 === $paren_count && !$this->is_token([T_COMMENT, T_DOC_COMMENT])) {
							break;
						}
					}
					if (!$this->is_token(ST_CURLY_OPEN) && !$this->is_token(ST_COLON)) {
						$ignore_count = 0;
						if (!$this->is_token([T_COMMENT, T_DOC_COMMENT], true)) {
							$this->append_code($this->new_line . '{');
						} else {
							$this->append_code('{');
						}
						while (list($index, $token) = each($this->tkns)) {
							list($id, $text) = $this->get_token($token);
							$this->ptr = $index;

							if (ST_QUOTE == $id) {
								$this->append_code($text, false);
								while (list($index, $token) = each($this->tkns)) {
									list($id, $text) = $this->get_token($token);
									$this->ptr = $index;

									$this->append_code($text, false);
									if (ST_QUOTE == $id) {
										break;
									}
								}
								continue;
							}

							if (ST_PARENTHESES_OPEN === $id || ST_CURLY_OPEN === $id || ST_BRACKET_OPEN === $id) {
								++$ignore_count;
							} elseif (ST_PARENTHESES_CLOSE === $id || ST_CURLY_CLOSE === $id || ST_BRACKET_CLOSE === $id) {
								$ignore_count--;
							}
							$this->append_code($text, false);
							if ($ignore_count <= 0 && !($this->is_token(ST_CURLY_CLOSE) || $this->is_token(ST_SEMI_COLON) || $this->is_token([T_WHILE])) && (ST_CURLY_CLOSE === $id || ST_SEMI_COLON === $id || T_ELSE === $id || T_ELSEIF === $id)) {
								break;
							}
						}
						$this->append_code($this->get_crlf_indent() . '}' . $this->get_crlf_indent(), false);
						break 2;
					}
					break;
				case T_IF:
				case T_ELSEIF:
					$this->append_code($text, false);
					$paren_count = null;
					while (list($index, $token) = each($this->tkns)) {
						list($id, $text) = $this->get_token($token);
						$this->ptr = $index;
						if (ST_PARENTHESES_OPEN === $id) {
							++$paren_count;
						} elseif (ST_PARENTHESES_CLOSE === $id) {
							$paren_count--;
						}
						$this->append_code($text, false);
						if (0 === $paren_count && !$this->is_token([T_COMMENT, T_DOC_COMMENT])) {
							break;
						}
					}
					if (!$this->is_token(ST_CURLY_OPEN) && !$this->is_token(ST_COLON)) {
						$ignore_count = 0;
						if (!$this->is_token([T_COMMENT, T_DOC_COMMENT], true)) {
							// $this->append_code($this->new_line.'{'.$this->new_line);
							$this->append_code($this->new_line . '{');
						} else {
							// $this->append_code('{'.$this->new_line);
							$this->append_code('{');
						}
						while (list($index, $token) = each($this->tkns)) {
							list($id, $text) = $this->get_token($token);
							$this->ptr = $index;

							if (ST_QUOTE == $id) {
								$this->append_code($text, false);
								while (list($index, $token) = each($this->tkns)) {
									list($id, $text) = $this->get_token($token);
									$this->ptr = $index;

									$this->append_code($text, false);
									if (ST_QUOTE == $id) {
										break;
									}
								}
								continue;
							}

							if (ST_PARENTHESES_OPEN === $id || ST_CURLY_OPEN === $id || ST_BRACKET_OPEN === $id) {
								++$ignore_count;
							} elseif (ST_PARENTHESES_CLOSE === $id || ST_CURLY_CLOSE === $id || ST_BRACKET_CLOSE === $id) {
								$ignore_count--;
							}
							$this->append_code($text, false);
							if ($ignore_count <= 0 && !($this->is_token(ST_CURLY_CLOSE) || $this->is_token(ST_SEMI_COLON) || $this->is_token([T_WHILE])) && (ST_CURLY_CLOSE === $id || ST_SEMI_COLON === $id || T_ELSE === $id || T_ELSEIF === $id)) {
								break;
							}
						}
						$this->append_code($this->get_crlf_indent() . '}' . $this->get_crlf_indent(), false);
						break 2;
					}
					break;
				case T_ELSE:
					$this->append_code($text, false);
					if (!$this->is_token(ST_CURLY_OPEN) && !$this->is_token(ST_COLON) && !$this->is_token([T_IF])) {
						$ignore_count = 0;
						$this->append_code('{' . $this->new_line);
						while (list($index, $token) = each($this->tkns)) {
							list($id, $text) = $this->get_token($token);
							$this->ptr = $index;

							if (ST_QUOTE == $id) {
								$this->append_code($text, false);
								while (list($index, $token) = each($this->tkns)) {
									list($id, $text) = $this->get_token($token);
									$this->ptr = $index;

									$this->append_code($text, false);
									if (ST_QUOTE == $id) {
										break;
									}
								}
								continue;
							}

							if (ST_PARENTHESES_OPEN === $id || ST_CURLY_OPEN === $id || ST_BRACKET_OPEN === $id) {
								++$ignore_count;
							} elseif (ST_PARENTHESES_CLOSE === $id || ST_CURLY_CLOSE === $id || ST_BRACKET_CLOSE === $id) {
								$ignore_count--;
							}
							$this->append_code($text, false);
							if ($ignore_count <= 0 && !($this->is_token(ST_CURLY_CLOSE) || $this->is_token(ST_SEMI_COLON) || $this->is_token([T_WHILE])) && (ST_CURLY_CLOSE === $id || ST_SEMI_COLON === $id || T_ELSE === $id || T_ELSEIF === $id)) {
								break;
							}
						}
						$this->append_code($this->get_crlf_indent() . '}' . $this->get_crlf_indent(), false);
						break 2;
					}
					break;
				default:
					$this->append_code($text, false);
					break;
			}
		}
		while (list($index, $token) = each($this->tkns)) {
			list($id, $text) = $this->get_token($token);
			$this->ptr = $index;
			$this->append_code($text, false);
		}

		return $this->code;
	}
}
