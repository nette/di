<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\DI\Definitions;

use Nette;
use Nette\DI\ServiceCreationException;


/**
 * Definition of standard service.
 *
 * @property-deprecated string|null $class
 * @property-deprecated Statement $factory
 * @property-deprecated Statement[] $setup
 */
final class ServiceDefinition extends Definition
{
	use Nette\SmartObject;

	private Statement $creator;

	/** @var Statement[] */
	private array $setup = [];


	public function __construct()
	{
		$this->creator = new Statement(null);
	}


	public function getDescriptor(): string
	{
		$entity = $this->getEntity();
		if ($entity && $this->isAnonymous()) {
			return 'Service ' . (is_string($entity) ? "of type $entity" : Nette\DI\Helpers::entityToString($entity));
		}

		return parent::getDescriptor();
	}


	public function setType(?string $type): static
	{
		return parent::setType($type);
	}


	/**
	 * Alias for setCreator()
	 */
	public function setFactory(string|array|Definition|Reference|Statement $factory, array $args = []): static
	{
		return $this->setCreator($factory, $args);
	}


	/**
	 * Alias for getCreator()
	 */
	public function getFactory(): Statement
	{
		return $this->getCreator();
	}


	public function setCreator(string|array|Definition|Reference|Statement $creator, array $args = []): static
	{
		$this->creator = $creator instanceof Statement
			? $creator
			: new Statement($creator, $args);
		return $this;
	}


	public function getCreator(): Statement
	{
		return $this->creator;
	}


	public function getEntity(): string|array|Definition|Reference|null
	{
		return $this->creator->getEntity();
	}


	public function setArguments(array $args = []): static
	{
		$this->creator->arguments = $args;
		return $this;
	}


	public function setArgument($key, $value): static
	{
		$this->creator->arguments[$key] = $value;
		return $this;
	}


	/**
	 * @param  Statement[]  $setup
	 */
	public function setSetup(array $setup): static
	{
		foreach ($setup as &$entity) {
			if (!$entity instanceof Statement) {
				throw new Nette\InvalidArgumentException('Argument must be Nette\DI\Definitions\Statement[].');
			}
			$entity = $this->prependSelf($entity);
		}

		$this->setup = $setup;
		return $this;
	}


	/** @return Statement[] */
	public function getSetup(): array
	{
		return $this->setup;
	}


	public function addSetup(string|array|Definition|Reference|Statement $entity, array $args = []): static
	{
		$entity = $entity instanceof Statement
			? $entity
			: new Statement($entity, $args);
		$this->setup[] = $this->prependSelf($entity);
		return $this;
	}


	public function resolveType(Nette\DI\Resolver $resolver): void
	{
		if (!$this->getEntity()) {
			if (!$this->getType()) {
				throw new ServiceCreationException('Factory and type are missing in definition of service.');
			}

			$this->setCreator($this->getType(), $this->creator->arguments ?? []);

		} elseif (!$this->getType()) {
			$type = $resolver->resolveEntityType($this->creator);
			if (!$type) {
				throw new ServiceCreationException('Unknown service type, specify it or declare return type of factory method.');
			}

			$this->setType($type);
			$resolver->addDependency(new \ReflectionClass($type));
		}

		// auto-disable autowiring for aliases
		if ($this->getAutowired() === true && $this->getEntity() instanceof Reference) {
			$this->setAutowired(false);
		}
	}


	public function complete(Nette\DI\Resolver $resolver): void
	{
		$entity = $this->creator->getEntity();
        foreach($this->creator->arguments as &$statement){
            if(is_string($statement)){
                if(str_starts_with($statement, '@') && count(explode('::', $statement)) >= 3){   // omit @service::member
                    $statement = $this->parseChain($statement);
                }
            }elseif($statement instanceof Statement){
                $this->normalizeStatement($statement);
            }
        }
        if ($entity instanceof Reference && !$this->creator->arguments && !$this->setup) {
			$ref = $resolver->normalizeReference($entity);
			$this->setCreator([new Reference(Nette\DI\ContainerBuilder::ThisContainer), 'getService'], [$ref->getValue()]);
		}

		$this->creator = $resolver->completeStatement($this->creator);

		foreach ($this->setup as &$setup) {
            foreach($setup->arguments as &$statement){
                if(is_string($statement)){
                    if(str_starts_with($statement, '@') && count(explode('::', $statement)) >= 3){
                        $statement = $this->parseChain($statement);
                    }
                }elseif($statement instanceof Statement){
                    $this->normalizeStatement($statement);
                }
            }
			$setup = $resolver->completeStatement($setup, true);
		}
	}


    private function parseChain(string $statement): Statement
    {
        $members = explode('::', $statement);
        $entity = [];
        $entity[0] = new Reference(substr(array_shift($members), 1));
        while($property = array_shift($members)){
            $entity[1] = '$'.$property;      // in chain all members are properties
            $statement = new Statement($entity);
            $entity = [];
            $entity[0] = $statement;
        }
        return $statement;
    }


    private function parseSubChain(Statement $statement): Statement
    {
        $entity = $statement->entity;
        if($entity[0] instanceof Reference && !str_ends_with($entity[1], '()')){
            $entity[1] .= '()';     // fix different syntax
        }
        $members = explode('::', $entity[1]);
        while($member = array_shift($members)){
            if(count($members) >= 1){
                $member = '$'.$member;

            }elseif(str_ends_with($member, '()')){
                $member = substr($member, 0, -2);

            }elseif($entity[0] instanceof Statement){
                $member = '$'.$member;
            }

            $entity[1] = $member;
            $statement = new Statement($entity);
            $entity = [];
            $entity[0] = $statement;
        }

        return $statement;
    }


    private function normalizeStatement(Statement &$input, bool $recursive = false): void  // & needed
    {
        if($recursive){
            $statement = $input->entity[0];

        }else{
            $statement = $input;
        }
        if(is_array($statement->entity)){
            $entity = $statement->entity;
            if($entity[0] instanceof Statement){
                if(is_string($entity[1])){
                    if(str_contains($entity[1], '::')){
                        $statement = $this->parseSubChain($statement);
                        if($recursive){
                            $input->setEntityStatement($statement);

                        }else{
                            $input = $statement;
                        }

                    }else{
                        $this->normalizeMember($statement);
                        $this->normalizeStatement($statement, recursive: true);
                    }
                }

            }elseif($entity[0] instanceof Reference){
                $statement = $this->parseSubChain($statement);
                if($recursive){
                    $input->setEntityStatement($statement);

                }else{
                    $input = $statement;
                }

            }
        }
    }


    private function normalizeMember(Statement $statement): void
    {
        $entity = $statement->entity;
        $normalizedValue = str_ends_with($entity[1], '()') ? substr($entity[1], 0, -2) : '$'.$entity[1];
        $statement->setEntityMember($normalizedValue);
    }


	private function prependSelf(Statement $setup): Statement
	{
		return is_string($setup->getEntity()) && strpbrk($setup->getEntity(), ':@?\\') === false
			? new Statement([new Reference(Reference::Self), $setup->getEntity()], $setup->arguments)
			: $setup;
	}


	public function generateMethod(Nette\PhpGenerator\Method $method, Nette\DI\PhpGenerator $generator): void
	{
		$code = $generator->formatStatement($this->creator) . ";\n";
		if (!$this->setup) {
			$method->setBody('return ' . $code);
			return;
		}

		$code = '$service = ' . $code;
		foreach ($this->setup as $setup) {
			$code .= $generator->formatStatement($setup) . ";\n";
		}

		$code .= 'return $service;';
		$method->setBody($code);
	}


	public function __clone()
	{
		parent::__clone();
		$this->creator = unserialize(serialize($this->creator));
		$this->setup = unserialize(serialize($this->setup));
	}
}
