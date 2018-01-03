<?php declare(strict_types = 1);

namespace SlevomatCodingStandard\Sniffs\Namespaces;

use SlevomatCodingStandard\Helpers\NamespaceHelper;
use SlevomatCodingStandard\Helpers\ReferencedNameHelper;
use SlevomatCodingStandard\Helpers\SniffSettingsHelper;
use SlevomatCodingStandard\Helpers\UseStatementHelper;

class FullyQualifiedGlobalFunctionsSniff implements \PHP_CodeSniffer\Sniffs\Sniff
{

	public const CODE_NON_FULLY_QUALIFIED = 'NonFullyQualified';

	/** @var string[] */
	public $exclude = [];

	/** @var string[]|null */
	private $normalizedExclude;

	/**
	 * @return mixed[]
	 */
	public function register(): array
	{
		return [
			T_OPEN_TAG,
		];
	}

	/**
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	 * @param \PHP_CodeSniffer\Files\File $phpcsFile
	 * @param int $openTagPointer
	 */
	public function process(\PHP_CodeSniffer\Files\File $phpcsFile, $openTagPointer): void
	{
		$tokens = $phpcsFile->getTokens();

		$referencedNames = ReferencedNameHelper::getAllReferencedNames($phpcsFile, $openTagPointer);
		$useStatements = UseStatementHelper::getUseStatements($phpcsFile, $openTagPointer);
		$exclude = array_flip($this->getNormalizedExclude());

		foreach ($referencedNames as $referencedName) {
			$name = $referencedName->getNameAsReferencedInFile();
			$canonicalName = strtolower($name);
			$referenceNamePointer = $referencedName->getStartPointer();

			if (!$referencedName->isFunction()) {
				continue;
			}

			if (NamespaceHelper::isFullyQualifiedName($name)) {
				continue;
			}

			if (NamespaceHelper::hasNamespace($name)) {
				continue;
			}

			if (array_key_exists($canonicalName, $useStatements)) {
				continue;
			}

			if (array_key_exists($canonicalName, $exclude)) {
				continue;
			}

			$fix = $phpcsFile->addFixableError(sprintf('Function %s() should be referenced via a fully qualified name.', $tokens[$referenceNamePointer]['content']), $referenceNamePointer, self::CODE_NON_FULLY_QUALIFIED);
			if ($fix) {
				$phpcsFile->fixer->beginChangeset();
				$phpcsFile->fixer->addContentBefore($referenceNamePointer, NamespaceHelper::NAMESPACE_SEPARATOR);
				$phpcsFile->fixer->endChangeset();
			}
		}
	}

	/**
	 * @return string[]
	 */
	private function getNormalizedExclude(): array
	{
		if ($this->normalizedExclude === null) {
			$exclude = array_map(function (string $name): string {
				return strtolower($name);
			}, SniffSettingsHelper::normalizeArray($this->exclude));

			$this->normalizedExclude = $exclude;
		}
		return $this->normalizedExclude;
	}

}
