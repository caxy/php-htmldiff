<?php

	class HtmlDiff {

		private $content;
		private $oldText;
		private $newText;
		private $oldWords = array();
		private $newWords = array();
		private $wordIndices;
		private $specialCaseOpeningTags = array( "<strong[\\>\\s]+", "<b[\\>\\s]+", "<i[\\>\\s]+", "<big[\\>\\s]+", "<small[\\>\\s]+", "<u[\\>\\s]+", "<sub[\\>\\s]+", "<sup[\\>\\s]+", "<strike[\\>\\s]+", "<s[\\>\\s]+", '<p[\\>\\s]+' );
		private $specialCaseClosingTags = array( "</strong>", "</b>", "</i>", "</big>", "</small>", "</u>", "</sub>", "</sup>", "</strike>", "</s>", '</p>' );

		public function __construct( $oldText, $newText) {
			$this->oldText = trim( $oldText );
			$this->newText = trim( $newText );
			$this->content = '';
		}

		public function build() {
			$this->SplitInputsToWords();
			$this->IndexNewWords();
			$operations = $this->Operations();
			foreach( $operations as $item ) {
				$this->PerformOperation( $item );
			}
			return $this->content;
		}

		private function IndexNewWords() {
			$this->wordIndices = array();
			foreach( $this->newWords as $i => $word ) {
				if( $this->IsTag( $word ) ) {
					$word = $this->StripTagAttributes( $word );
				}
				if( isset( $this->wordIndices[ $word ] ) ) {
					$this->wordIndices[ $word ][] = $i;
				} else {
					$this->wordIndices[ $word ] = array( $i );
				}
			}
		}

		private function SplitInputsToWords() {
			$this->oldWords = $this->ConvertHtmlToListOfWords( $this->Explode( $this->oldText ) );
			$this->newWords = $this->ConvertHtmlToListOfWords( $this->Explode( $this->newText ) );
		}

		private function ConvertHtmlToListOfWords( $characterString ) {
			$mode = 'character';
			$current_word = '';
			$words = array();
			foreach( $characterString as $character ) {
				switch ( $mode ) {
					case 'character':
						if( $this->IsStartOfTag( $character ) ) {
							if( $current_word != '' ) {
								$words[] = $current_word;
							}
							$current_word = "<";
							$mode = 'tag';
						} else if( preg_match( "[^\s]", $character ) > 0 ) {
							if( $current_word != '' ) {
								$words[] = $current_word;
							}
							$current_word = $character;
							$mode = 'whitespace';
						} else {
							if( ctype_alnum( $character ) ) {
								$current_word .= $character;
							} else {
								$words[] = $current_word;
								$current_word = $character;
							}
						}
						break;
					case 'tag' :
						if( $this->IsEndOfTag( $character ) ) {
							$current_word .= ">";
							$words[] = $current_word;
							$current_word = "";

							if( !preg_match('[^\s]', $character ) ) {
								$mode = 'whitespace';
							} else {
								$mode = 'character';
							}
						} else {
							$current_word .= $character;
						}
						break;
					case 'whitespace':
						if( $this->IsStartOfTag( $character ) ) {
							if( $current_word != '' ) {
								$words[] = $current_word;
							}
							$current_word = "<";
							$mode = 'tag';
						} else if( preg_match( "[^\s]", $character ) ) {
							$current_word .= $character;
						} else {
							if( $current_word != '' ) {
								$words[] = $current_word;
							}
							$current_word = $character;
							$mode = 'character';
						}
						break;
					default:
						break;
				}
			}
			if( $current_word != '' ) {
				$words[] = $current_word;
			}
			return $words;
		}

		private function IsStartOfTag( $val ) {
			return $val == "<";
		}

		private function IsEndOfTag( $val ) {
			return $val == ">";
		}

		private function IsWhiteSpace( $value ) {
			return !preg_match( '[^\s]', $value );
		}

		private function Explode( $value ) {
			return str_split( $value );
		}

		private function PerformOperation( $operation ) {
			switch( $operation->Action ) {
				case 'equal' :
					$this->ProcessEqualOperation( $operation );
					break;
				case 'delete' :
					$this->ProcessDeleteOperation( $operation, "diffdel" );
					break;
				case 'insert' :
					$this->ProcessInsertOperation( $operation, "diffins");
					break;
				case 'replace':
					$this->ProcessReplaceOperation( $operation );
					break;
				default:
					break;
			}
		}

		private function ProcessReplaceOperation( $operation ) {
			$this->ProcessDeleteOperation( $operation, "diffmod" );
			$this->ProcessInsertOperation( $operation, "diffmod" );
		}

		private function ProcessInsertOperation( $operation, $cssClass ) {
			$text = array();
			foreach( $this->newWords as $pos => $s ) {
				if( $pos >= $operation->StartInNew && $pos < $operation->EndInNew ) {
					$text[] = $s;
				}
			}
			$this->InsertTag( "ins", $cssClass, $text );
		}

		private function ProcessDeleteOperation( $operation, $cssClass ) {
			$text = array();
			foreach( $this->oldWords as $pos => $s ) {
				if( $pos >= $operation->StartInOld && $pos < $operation->EndInOld ) {
					$text[] = $s;
				}
			}
			$this->InsertTag( "del", $cssClass, $text );
		}

		private function ProcessEqualOperation( $operation ) {
			$result = array();
			foreach( $this->newWords as $pos => $s ) {
				if( $pos >= $operation->StartInNew && $pos < $operation->EndInNew ) {
					$result[] = $s;
				}
			}
			$this->content .= implode( "", $result );
		}

		private function InsertTag( $tag, $cssClass, &$words ) {
			while( true ) {
				if( count( $words ) == 0 ) {
					break;
				}

				$nonTags = $this->ExtractConsecutiveWords( $words, 'noTag' );

				$specialCaseTagInjection = '';
				$specialCaseTagInjectionIsBefore = false;

				if( count( $nonTags ) != 0 ) {
					$text = $this->WrapText( implode( "", $nonTags ), $tag, $cssClass );
					$this->content .= $text;
				} else {
					$firstOrDefault = false;
					foreach( $this->specialCaseOpeningTags as $x ) {
						if( preg_match( $words[ 0 ], $x ) ) {
							$firstOrDefault = $x;
							break;
						}
					}
					if( $firstOrDefault ) {
						$specialCaseTagInjection = "<ins class='mod'>";
						if( $tag == "del" ) {
							unset( $words[ 0 ] );
						}
					} else if( array_search( $words[ 0 ], $this->specialCaseClosingTags ) !== false ) {
						$specialCaseTagInjection = "</ins>";
						$specialCaseTagInjectionIsBefore = true;
						if( $tag == "del" ) {
							unset( $words[ 0 ] );
						}
					}
				}
				if( count( $words ) == 0 && count( $specialCaseTagInjection ) == 0 ) {
					break;
				}
				if( $specialCaseTagInjectionIsBefore ) {
					$this->content .= $specialCaseTagInjection . implode( "", $this->ExtractConsecutiveWords( $words, 'tag' ) );
				} else {
					$this->content .= implode( "", $this->ExtractConsecutiveWords( $words, 'tag' ) ) . $specialCaseTagInjection;
				}
			}
		}

		private function checkCondition( $word, $condition ) {
			return $condition == 'tag' ? $this->IsTag( $word ) : !$this->IsTag( $word );
		}

		private function WrapText( $text, $tagName, $cssClass ) {
			return sprintf( '<%1$s class="%2$s">%3$s</%1$s>', $tagName, $cssClass, $text );
		}

		private function ExtractConsecutiveWords( &$words, $condition ) {
			$indexOfFirstTag = null;
			foreach( $words as $i => $word ) {
				if( !$this->checkCondition( $word, $condition ) ) {
					$indexOfFirstTag = $i;
					break;
				}
			}
			if( $indexOfFirstTag !== null ) {
				$items = array();
				foreach( $words as $pos => $s ) {
					if( $pos >= 0 && $pos < $indexOfFirstTag ) {
						$items[] = $s;
					}
				}
				if( $indexOfFirstTag > 0 ) {
					array_splice( $words, 0, $indexOfFirstTag );
				}
				return $items;
			} else {
				$items = array();
				foreach( $words as $pos => $s ) {
					if( $pos >= 0 && $pos <= count( $words ) ) {
						$items[] = $s;
					}
				}
				array_splice( $words, 0, count( $words ) );
				return $items;
			}
		}

		private function IsTag( $item ) {
			return $this->IsOpeningTag( $item ) || $this->IsClosingTag( $item );
		}

		private function IsOpeningTag( $item ) {
			return preg_match( "#<[^>]+>\\s*#iU", $item );
		}

		private function IsClosingTag( $item ) {
			return preg_match( "#</[^>]+>\\s*#iU", $item );
		}

		private function Operations() {
			$positionInOld = 0;
			$positionInNew = 0;
			$operations = array();
			$matches = $this->MatchingBlocks();
			$matches[] = new Match( count( $this->oldWords ), count( $this->newWords ), 0 );
			foreach(  $matches as $i => $match ) {
				$matchStartsAtCurrentPositionInOld = ( $positionInOld == $match->StartInOld );
				$matchStartsAtCurrentPositionInNew = ( $positionInNew == $match->StartInNew );
				$action = 'none';

				if( $matchStartsAtCurrentPositionInOld == false && $matchStartsAtCurrentPositionInNew == false ) {
					$action = 'replace';
				} else if( $matchStartsAtCurrentPositionInOld == true && $matchStartsAtCurrentPositionInNew == false ) {
					$action = 'insert';
				} else if( $matchStartsAtCurrentPositionInOld == false && $matchStartsAtCurrentPositionInNew == true ) {
					$action = 'delete';
				} else { // This occurs if the first few words are the same in both versions
					$action = 'none';
				}
				if( $action != 'none' ) {
					$operations[] = new Operation( $action, $positionInOld, $match->StartInOld, $positionInNew, $match->StartInNew );
				}
				if( count( $match ) != 0 ) {
					$operations[] = new Operation( 'equal', $match->StartInOld, $match->EndInOld(), $match->StartInNew, $match->EndInNew() );
				}
				$positionInOld = $match->EndInOld();
				$positionInNew = $match->EndInNew();
			}
			return $operations;
		}

		private function MatchingBlocks() {
			$matchingBlocks = array();
			$this->FindMatchingBlocks( 0, count( $this->oldWords ), 0, count( $this->newWords ), $matchingBlocks );
			return $matchingBlocks;
		}

		private function FindMatchingBlocks( $startInOld, $endInOld, $startInNew, $endInNew, &$matchingBlocks ) {
			$match = $this->FindMatch( $startInOld, $endInOld, $startInNew, $endInNew );
			if( $match !== null ) {
				if( $startInOld < $match->StartInOld && $startInNew < $match->StartInNew ) {
					$this->FindMatchingBlocks( $startInOld, $match->StartInOld, $startInNew, $match->StartInNew, $matchingBlocks );
				}
				$matchingBlocks[] = $match;
				if( $match->EndInOld() < $endInOld && $match->EndInNew() < $endInNew ) {
					$this->FindMatchingBlocks( $match->EndInOld(), $endInOld, $match->EndInNew(), $endInNew, $matchingBlocks );
				}
			}
		}

		private function StripTagAttributes( $word ) {
			$word = explode( ' ', trim( $word, '<>' ) );
			return '<' . $word[ 0 ] . '>';
		}

		private function FindMatch( $startInOld, $endInOld, $startInNew, $endInNew ) {
			$bestMatchInOld = $startInOld;
			$bestMatchInNew = $startInNew;
			$bestMatchSize = 0;
			$matchLengthAt = array();
			for( $indexInOld = $startInOld; $indexInOld < $endInOld; $indexInOld++ ) {
				$newMatchLengthAt = array();
				$index = $this->oldWords[ $indexInOld ];
				if( $this->IsTag( $index ) ) {
					$index = $this->StripTagAttributes( $index );
				}
				if( !isset( $this->wordIndices[ $index ] ) ) {
					$matchLengthAt = $newMatchLengthAt;
					continue;
				}
				foreach( $this->wordIndices[ $index ] as $indexInNew ) {
					if( $indexInNew < $startInNew ) {
						continue;
					}
					if( $indexInNew >= $endInNew ) {
						break;
					}
					$newMatchLength = ( isset( $matchLengthAt[ $indexInNew - 1 ] ) ? $matchLengthAt[ $indexInNew - 1 ] : 0 ) + 1;
					$newMatchLengthAt[ $indexInNew ] = $newMatchLength;
					if( $newMatchLength > $bestMatchSize ) {
						$bestMatchInOld = $indexInOld - $newMatchLength + 1;
						$bestMatchInNew = $indexInNew - $newMatchLength + 1;
						$bestMatchSize = $newMatchLength;
					}
				}
				$matchLengthAt = $newMatchLengthAt;
			}
			return $bestMatchSize != 0 ? new Match( $bestMatchInOld, $bestMatchInNew, $bestMatchSize ) : null;
		}
	}

	class Match {

		public $StartInOld;
		public $StartInNew;
		public $Size;

		public function __construct( $startInOld, $startInNew, $size ) {
			$this->StartInOld = $startInOld;
			$this->StartInNew = $startInNew;
			$this->Size = $size;
		}

		public function EndInOld() {
			return $this->StartInOld + $this->Size;
		}

		public function EndInNew() {
			return $this->StartInNew + $this->Size;
		}
	}

	class Operation {

		public $Action;
		public $StartInOld;
		public $EndInOld;
		public $StartInNew;
		public $EndInNew;

		public function __construct( $action, $startInOld, $endInOld, $startInNew, $endInNew ) {
			$this->Action = $action;
			$this->StartInOld = $startInOld;
			$this->EndInOld = $endInOld;
			$this->StartInNew = $startInNew;
			$this->EndInNew = $endInNew;
		}
	}
