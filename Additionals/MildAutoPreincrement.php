<?php
class MildAutoPreincrement extends AutoPreincrement {
	protected $candidate_tokens = [];
	protected $check_against_concat = true;
}