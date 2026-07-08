<?php declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\DI;

use Nette;
use function class_exists, file_get_contents, file_put_contents, flock, fopen, function_exists, hash, is_file, rename, serialize, sprintf, strlen, substr, unlink, unserialize, usleep;


/**
 * DI container loader.
 */
class ContainerLoader
{
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
		if (!class_exists($class, autoload: false)) {
			$this->loadFile($class, $generator(...));
		}

		return $class;
	}


	/**
	 * Returns the container class name derived from the given key.
	 * @return class-string<Container>
	 */
	public function getClassName(mixed $key): string
	{
		return 'Container_' . substr(hash('xxh128', serialize($key)), 0, 10);
	}


	/** @param  (\Closure(Compiler): ?string)  $generator */
	private function loadFile(string $class, \Closure $generator): void
	{
		$file = "$this->tempDirectory/$class.php";
		if (!$this->isExpired($file) && (@include $file) !== false) { // @ file may not exist
			return;
		}

		Nette\Utils\FileSystem::createDir($this->tempDirectory);

		$handle = @fopen("$file.lock", 'c+'); // @ is escalated to exception
		if (!$handle) {
			throw new Nette\IOException(sprintf("Unable to create file '%s.lock'. %s", $file, Nette\Utils\Helpers::getLastError()));
		} elseif (!@flock($handle, LOCK_EX)) { // @ is escalated to exception
			throw new Nette\IOException(sprintf("Unable to acquire exclusive lock on '%s.lock'. %s", $file, Nette\Utils\Helpers::getLastError()));
		}

		if (!is_file($file) || $this->isExpired($file, $updatedMeta)) {
			if (isset($updatedMeta)) {
				$toWrite["$file.meta"] = $updatedMeta;
			} else {
				[$toWrite[$file], $toWrite["$file.meta"]] = $this->generate($class, $generator);
			}

			foreach ($toWrite as $name => $content) {
				$this->atomicWrite($name, $content);
			}
		}

		if ((@include $file) === false) { // @ - error escalated to exception
			throw new Nette\IOException(sprintf("Unable to include '%s'.", $file));
		}
		flock($handle, LOCK_UN);
	}


	/**
	 * Atomically writes $content to $file through a temporary file and rename().
	 *
	 * On Windows the rename intermittently fails with "Access is denied" when the target is
	 * momentarily locked (antivirus or a memory-mapped opcache handle); unlike POSIX the replace
	 * is not atomic against open handles. So the opcache handle is dropped first and the rename
	 * retried briefly. Elsewhere a single failure throws at once.
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
		if ($this->autoRebuild) {
			$meta = @unserialize((string) file_get_contents("$file.meta")); // @ - file may not exist
			$orig = $meta[2] ?? null;
			return empty($meta[0])
				|| DependencyChecker::isExpired(...$meta)
				|| ($orig !== $meta[2] && $updatedMeta = serialize($meta));
		}

		return false;
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
