<?php
final class TwoCommandsInSameLine extends FormatterPass {
	public function format($source) {
		$lines = explode($this->new_line, $source);
		foreach ($lines as $idx => $line) {
			if (substr_count($line, ';') <= 1) {
				continue;
			}
			$new_line           = '';
			$ignore_stack       = 0;
			$double_quote_state = false;
			$single_quote_state = false;
			$len                = strlen($line);
			for ($i = 0; $i < $len; $i++) {
				$char = substr($line, $i, 1);
				if (ST_PARENTHESES_OPEN === $char || ST_PARENTHESES_OPEN === $char || ST_CURLY_OPEN === $char || ST_BRACKET_OPEN === $char) {
					$ignore_stack++;
				}
				if (ST_PARENTHESES_CLOSE === $char || ST_CURLY_CLOSE === $char || ST_BRACKET_CLOSE === $char) {
					$ignore_stack--;
				}
				if ('"' === $char) {
					$double_quote_state = !$double_quote_state;
				}
				if ("'" === $char) {
					$single_quote_state = !$single_quote_state;
				}
				$new_line .= $char;
				if (!$single_quote_state && !$double_quote_state && 0 === $ignore_stack && ST_SEMI_COLON === $char && $i + 1 < $len) {
					$new_line .= $this->new_line;
				}
			}
			$lines[$idx] = $new_line;
		}
		return implode($this->new_line, $lines);
	}
}