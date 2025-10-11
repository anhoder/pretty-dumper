<?php

declare(strict_types=1);

// Stubs required only for static analysis where optional framework packages are not installed.

namespace Illuminate\Contracts\Container {
    /**
     * @extends \ArrayAccess<string, mixed>
     */
    interface Container extends \ArrayAccess
    {
        public function singleton(string $abstract, callable $concrete): void;

        public function bind(string $abstract, callable $concrete): void;

        public function make(string $abstract): mixed;
    }
}

namespace Illuminate\Support {
    use Illuminate\Contracts\Container\Container;

    if (!class_exists(ServiceProvider::class)) {
        abstract class ServiceProvider
        {
            public function __construct(protected Container $app)
            {
            }

            public function register(): void
            {
            }

            public function boot(): void
            {
            }

            public function publishes(array $paths, string $group = 'default'): void
            {
            }
        }
    }
}

namespace Symfony\Component\DependencyInjection {
    if (!class_exists(ContainerBuilder::class)) {
        class ContainerBuilder
        {
            /** @var array<string, mixed> */
            private array $parameters = [];

            public function hasParameter(string $name): bool
            {
                return array_key_exists($name, $this->parameters);
            }

            public function setParameter(string $name, mixed $value): void
            {
                $this->parameters[$name] = $value;
            }

            public function register(string $id, string $class): self
            {
                return $this;
            }

            public function setFactory(array $factory): self
            {
                return $this;
            }

            public function addArgument(mixed $argument): self
            {
                return $this;
            }
        }
    }

    if (!class_exists(Reference::class)) {
        class Reference
        {
            public function __construct(private string $id)
            {
            }
        }
    }
}

namespace Symfony\Component\HttpKernel\Bundle {
    if (!class_exists(Bundle::class)) {
        class Bundle
        {
            public function build(mixed $container): void
            {
            }
        }
    }
}

