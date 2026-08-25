<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI;

use Nette;
use function is_string, sprintf, strlen;


/**
 * DI container loader.
 */
class ContainerLoader
{
	/** @var array<string, array<string, string>>  compiled file => [content hash => loaded class] (auto-rebuild only) */
	private static array $loaded = [];


	public function __construct(
		private readonly string $tempDirectory,
		private readonly bool $autoRebuild = false,
	) {
	}


	/**
	 * Loads the container class, generating it if not already cached. Returns the class name.
	 * @param  callable(Compiler): ?string  $generator
	 * @return class-string<Container>
	 */
	public function load(callable $generator, mixed $key = null): string
	{
		$class = $this->getClassName($key);
		$file = "$this->tempDirectory/$class.php";
		if ($this->autoRebuild) {
			return $this->loadCurrent($class, $file, $generator(...));
		} else {
			$this->loadOnce($class, $file, $generator(...));
			return $class;
		}
	}


	/**
	 * Returns the base class name derived from the given key (with auto-rebuild an alias of the first loaded container).
	 */
	public function getClassName(mixed $key): string
	{
		return 'Container_' . substr(hash('xxh128', serialize($key)), 0, 10);
	}


	/**
	 * Includes the file at most once; it is never rebuilt.
	 * @param  (\Closure(Compiler): ?string)  $generator
	 */
	private function loadOnce(string $class, string $file, \Closure $generator): void
	{
		if (class_exists($class, autoload: false) || (@include $file) !== false) { // @ - file may not exist
			return;
		}

		$this->withLock($file, function () use ($class, $file, $generator): void {
			if (!is_file($file)) {
				$this->writeContainer($class, $file, $generator);
			}

			if ((@include $file) === false) { // @ - error escalated to exception
				throw new Nette\IOException(sprintf("Unable to include '%s'.", $file));
			}
		});
	}


	/**
	 * Rebuilds the file when expired and includes it whenever its content has not been loaded yet.
	 * Every build declares a fresh class (the file returns its name), as PHP cannot redeclare a loaded one.
	 * @param  (\Closure(Compiler): ?string)  $generator
	 */
	private function loadCurrent(string $class, string $file, \Closure $generator): string
	{
		if (isset(self::$loaded[$file]) && !$this->isExpired($file)) {
			$code = @file_get_contents($file); // @ - file may not exist
			if ($code !== false && ($loaded = self::$loaded[$file][hash('xxh128', $code)] ?? null)) {
				return $loaded;
			}
		}

		return $this->withLock($file, function () use ($class, $file, $generator): string {
			if (!is_file($file) || $this->isExpired($file, $updatedMeta)) {
				if (isset($updatedMeta)) {
					$this->atomicWrite("$file.meta", $updatedMeta);
				} else {
					$unique = $class . '_' . bin2hex(random_bytes(4));
					$this->writeContainer($unique, $file, $generator, returnClass: $unique);
				}
			}

			$code = @file_get_contents($file); // @ - error escalated to exception
			if ($code === false) {
				throw new Nette\IOException(sprintf("Unable to read '%s'. %s", $file, Nette\Utils\Helpers::getLastError()));
			}

			return self::$loaded[$file][hash('xxh128', $code)] ??= $this->includeFreshFile($class, $file);
		});
	}


	/**
	 * Includes a file whose content has not been loaded yet and returns the class it declares.
	 */
	private function includeFreshFile(string $class, string $file): string
	{
		if (
			!isset(self::$loaded[$file])
			&& class_exists($class, autoload: false)
			&& realpath((new \ReflectionClass($class))->getFileName() ?: '') === realpath($file)
		) {
			return $class; // included outside of this loader
		}

		if (function_exists('opcache_invalidate')) {
			@opcache_invalidate($file, force: true); // @ can be restricted; the file may have been rebuilt by another process
		}

		$declared = @include $file; // @ - error escalated to exception
		if ($declared === false) {
			throw new Nette\IOException(sprintf("Unable to include '%s'.", $file));
		} elseif (!is_string($declared) || !class_exists($declared, autoload: false)) {
			$declared = $class; // the file declares $class itself: built without auto-rebuild or by a custom generator
		}

		if (!class_exists($declared, autoload: false)) {
			throw new Nette\InvalidStateException(sprintf("File '%s' does not declare class %s.", $file, $class));
		} elseif (!class_exists($class, autoload: false)) {
			class_alias($declared, $class); // getClassName() stays a valid name of the first loaded container
		}

		return $declared;
	}


	/** @param  (\Closure(Compiler): ?string)  $generator */
	private function writeContainer(string $class, string $file, \Closure $generator, ?string $returnClass = null): void
	{
		[$code, $meta] = $this->generate($class, $generator);
		$this->atomicWrite($file, $returnClass ? "$code\nreturn " . var_export($returnClass, return: true) . ";\n" : $code);
		$this->atomicWrite("$file.meta", $meta);
	}


	/**
	 * Runs $fn holding an exclusive lock on the sibling .lock file (against concurrent compilation).
	 * @param  \Closure(): mixed  $fn
	 */
	private function withLock(string $file, \Closure $fn): mixed
	{
		Nette\Utils\FileSystem::createDir($this->tempDirectory);

		$handle = @fopen("$file.lock", 'c+'); // @ is escalated to exception
		if (!$handle) {
			throw new Nette\IOException(sprintf("Unable to create file '%s.lock'. %s", $file, Nette\Utils\Helpers::getLastError()));
		} elseif (!@flock($handle, LOCK_EX)) { // @ is escalated to exception
			throw new Nette\IOException(sprintf("Unable to acquire exclusive lock on '%s.lock'. %s", $file, Nette\Utils\Helpers::getLastError()));
		}

		try {
			return $fn();
		} finally {
			flock($handle, LOCK_UN);
		}
	}


	/**
	 * Atomically writes $content to $file through a temporary file and rename(); on Windows the rename
	 * is retried briefly, as the target may be momentarily locked by antivirus or opcache.
	 */
	private function atomicWrite(string $file, string $content): void
	{
		$tmp = "$file.tmp";
		if (file_put_contents($tmp, $content) !== strlen($content)) {
			@unlink($tmp); // @ - file may not exist
			throw new Nette\IOException(sprintf("Unable to create file '%s'. %s", $file, Nette\Utils\Helpers::getLastError()));
		}

		if (function_exists('opcache_invalidate')) {
			@opcache_invalidate($file, force: true); // @ can be restricted; frees a possible handle on the old target
		}

		for ($attempt = 1; !@rename($tmp, $file); $attempt++) { // @ is escalated to exception below
			if ($attempt >= 3 || !Nette\Utils\Helpers::IsWindows) {
				@unlink($tmp); // @ - file may not exist
				throw new Nette\IOException(sprintf("Unable to create file '%s'. %s", $file, Nette\Utils\Helpers::getLastError()));
			}
			usleep(100_000);
		}

		if (function_exists('opcache_invalidate')) {
			@opcache_invalidate($file, force: true); // @ can be restricted; refresh with the new content
		}
	}


	private function isExpired(string $file, ?string &$updatedMeta = null): bool
	{
		$meta = @unserialize((string) file_get_contents("$file.meta")); // @ - file may not exist
		$orig = $meta[2] ?? null;
		return empty($meta[0])
			|| DependencyChecker::isExpired(...$meta)
			|| ($orig !== $meta[2] && $updatedMeta = serialize($meta));
	}


	/**
	 * @param  callable(Compiler): ?string  $generator
	 * @return array{string, string} code, file
	 */
	protected function generate(string $class, callable $generator): array
	{
		$compiler = new Compiler;
		$compiler->setClassName($class);
		$code = $generator(...[&$compiler]) ?? $compiler->compile();
		return [
			"<?php\n$code",
			serialize($compiler->exportDependencies()),
		];
	}
}
