<?php declare(strict_types=1);

/**
 * Test: the mutation journal records every change to a service definition (via the generalized
 * notifier), across the new DSL verbs, the legacy verbs and direct access; and is silent during
 * resolve (internal autowiring mutations are not "what an extension did").
 */

use Nette\DI\ContainerBuilder;
use Tester\Assert;
use function Nette\DI\create;

require __DIR__ . '/../bootstrap.php';


class JrnA
{
}

class JrnB
{
	public function setX($x)
	{
	}
}


test('records add + mutations via new and legacy verbs', function () {
	$b = new ContainerBuilder;
	$def = $b->add('svc', create(JrnB::class));  // add + creator
	$def->setup('setX', [1]);                      // DSL verb -> addSetup primitive
	$def->addSetup('setX', [2]);                   // legacy verb
	$def->tag('a')->addTag('b', 'val');            // DSL + legacy tag
	$def->autowired(false);                        // DSL -> setAutowired

	$actions = array_map(fn($e) => $e['action'], $b->getJournal()->getBiography('svc'));
	Assert::same(['added', 'creator', 'setup', 'setup', 'tag', 'tag', 'autowired'], $actions);
});


test('the recorded value carries the payload', function () {
	$b = new ContainerBuilder;
	$def = $b->add('svc', create(JrnA::class));
	$def->tag('logger', 'monolog');

	$bio = $b->getJournal()->getBiography('svc');
	$tag = end($bio);
	Assert::same('tag', $tag['action']);
	Assert::same(['logger', 'monolog'], $tag['value']);
});


test('type change records; a no-op set does not', function () {
	$b = new ContainerBuilder;
	$def = $b->add('svc', create(JrnA::class));
	$before = count($b->getJournal()->getBiography('svc'));

	$def->setType(JrnB::class);       // real change -> recorded
	$def->setType(JrnB::class);       // no-op -> not recorded

	$actions = array_map(fn($e) => $e['action'], $b->getJournal()->getBiography('svc'));
	Assert::same(1, count(array_filter($actions, fn($a) => $a === 'type')));
});


test('journal is silent during resolve (internal type mutations excluded)', function () {
	$b = new ContainerBuilder;
	// creator with no explicit type: resolve() will set the type internally
	$b->add('svc', create(JrnA::class));
	$b->resolve();

	// no 'type' entry from the internal resolve mutation
	$actions = array_map(fn($e) => $e['action'], $b->getJournal()->getBiography('svc'));
	Assert::notContains('type', $actions);
});


test('separate services have separate biographies', function () {
	$b = new ContainerBuilder;
	$b->add('one', create(JrnA::class));
	$b->add('two', create(JrnB::class))->tag('t');

	Assert::same(['one'], array_values(array_unique(array_map(fn($e) => $e['service'], $b->getJournal()->getBiography('one')))));
	Assert::contains('tag', array_map(fn($e) => $e['action'], $b->getJournal()->getBiography('two')));
});
