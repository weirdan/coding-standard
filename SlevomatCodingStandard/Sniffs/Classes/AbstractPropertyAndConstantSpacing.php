<?php declare(strict_types = 1);

namespace SlevomatCodingStandard\Sniffs\Classes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use SlevomatCodingStandard\Helpers\TokenHelper;
use function assert;
use function in_array;
use const T_COMMENT;
use const T_CONST;
use const T_DOC_COMMENT_OPEN_TAG;
use const T_FUNCTION;
use const T_PRIVATE;
use const T_PROTECTED;
use const T_PUBLIC;
use const T_SEMICOLON;
use const T_STRING;
use const T_VAR;
use const T_VARIABLE;

/**
 * @internal
 */
abstract class AbstractPropertyAndConstantSpacing implements Sniff
{

	/** @var int */
	public $minLinesCountBeforeWithComment = 1;

	/** @var int */
	public $maxLinesCountBeforeWithComment = 1;

	/** @var int */
	public $minLinesCountBeforeWithoutComment = 0;

	/** @var int */
	public $maxLinesCountBeforeWithoutComment = 1;

	/**
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
	 * @param \PHP_CodeSniffer\Files\File $file
	 * @param int $pointer
	 */
	public function process(File $file, $pointer): int
	{
		$tokens = $file->getTokens();

		$nextFunctionPointer = TokenHelper::findNext($file, [T_FUNCTION, T_STRING, T_VARIABLE], $pointer + 1);
		if ($nextFunctionPointer === null || $tokens[$nextFunctionPointer]['code'] === T_FUNCTION) {
			return $nextFunctionPointer ?? $pointer;
		}

		$nextSemicolon = TokenHelper::findNext($file, [T_SEMICOLON], $pointer);
		assert($nextSemicolon !== null);

		$firstOnLinePointer = TokenHelper::findFirstTokenOnNextLine($file, $nextSemicolon);
		assert($firstOnLinePointer !== null);

		$nextFunctionPointer = TokenHelper::findNext($file, [T_FUNCTION, T_STRING, T_VARIABLE], $firstOnLinePointer);
		if ($nextFunctionPointer === null || $tokens[$nextFunctionPointer]['code'] === T_FUNCTION || $tokens[$nextFunctionPointer]['conditions'] !== $tokens[$pointer]['conditions']) {
			return $nextFunctionPointer ?? $firstOnLinePointer;
		}

		$types = [T_COMMENT, T_DOC_COMMENT_OPEN_TAG, T_CONST, T_VAR, T_PUBLIC, T_PROTECTED, T_PRIVATE];
		$nextPointer = TokenHelper::findNext($file, $types, $firstOnLinePointer);

		$linesBetween = $tokens[$nextPointer]['line'] - $tokens[$nextSemicolon]['line'] - 1;
		if (in_array($tokens[$nextPointer]['code'], [T_DOC_COMMENT_OPEN_TAG, T_COMMENT], true)) {
			$minExpectedLines = $this->minLinesCountBeforeWithComment;
			$maxExpectedLines = $this->maxLinesCountBeforeWithComment;
		} else {
			$minExpectedLines = $this->minLinesCountBeforeWithoutComment;
			$maxExpectedLines = $this->maxLinesCountBeforeWithoutComment;
		}

		if ($linesBetween >= $minExpectedLines && $linesBetween <= $maxExpectedLines) {
			return $firstOnLinePointer;
		}

		$fix = $this->addError($file, $pointer, $minExpectedLines, $maxExpectedLines, $linesBetween);
		if (! $fix) {
			return $firstOnLinePointer;
		}

		$file->fixer->beginChangeset();

		if ($linesBetween > $maxExpectedLines) {
			for ($i = $firstOnLinePointer; $i < $firstOnLinePointer + $maxExpectedLines; $i++) {
				$file->fixer->replaceToken($i, '');
			}
		} else {
			for ($i = 0; $i < $minExpectedLines; $i++) {
				$file->fixer->addNewlineBefore($firstOnLinePointer);
			}
		}

		$file->fixer->endChangeset();

		return $firstOnLinePointer;
	}

	abstract protected function addError(File $file, int $pointer, int $min, int $max, int $found): bool;

}
