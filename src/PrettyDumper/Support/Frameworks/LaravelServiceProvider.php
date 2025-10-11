<?php

declare(strict_types=1);

namespace PrettyDumper\Support\Frameworks;

use Illuminate\Contracts\Container\Container;
use PrettyDumper\Formatter\DumpRenderRequest;
use PrettyDumper\Formatter\FormatterConfiguration;
use PrettyDumper\Formatter\PrettyFormatter;
use PrettyDumper\Renderer\CliRenderer;

if (class_exists(\Illuminate\Support\ServiceProvider::class)) {
    class LaravelServiceProvider extends \Illuminate\Support\ServiceProvider
    {
        public function register(): void
        {
            /** @var Container $container */
            $container = $this->app;

            $container->singleton(FormatterConfiguration::class, function (Container $app): FormatterConfiguration {
                $config = $app['config'] ?? [];
                $options = [];

                if (is_array($config) && isset($config['pretty-dump']) && is_array($config['pretty-dump'])) {
                    $options = self::sanitizeOptions($config['pretty-dump']);
                }

                return new FormatterConfiguration($options);
            });

            $container->singleton(PrettyFormatter::class, function (Container $app): PrettyFormatter {
                /** @var FormatterConfiguration $configuration */
                $configuration = $app->make(FormatterConfiguration::class);

                return PrettyFormatter::forChannel('cli', $configuration);
            });

            $container->bind('pretty-dump', function (Container $app) {
                return function (mixed $value, array $options = []) use ($app): string {
                    /** @var PrettyFormatter $formatter */
                    $formatter = $app->make(PrettyFormatter::class);
                    $renderer = new CliRenderer($formatter, function_exists('stream_isatty') ? stream_isatty(STDOUT) : true);
                    $normalizedOptions = self::sanitizeOptions($options);
                    $request = new DumpRenderRequest($value, 'cli', $normalizedOptions);

                    return $renderer->render($request);
                };
            });
        }

        public function boot(): void
        {
            $configPath = __DIR__ . '/../../../config/pretty-dump.php';
            if (file_exists($configPath) && function_exists('config_path')) {
                $this->publishes([
                    $configPath => config_path('pretty-dump.php'),
                ], 'pretty-dump-config');
            }
        }

        /**
         * @param array<int|string, mixed> $options
         * @return array<string, mixed>
         */
        private static function sanitizeOptions(array $options): array
        {
            $sanitized = [];

            foreach ($options as $key => $value) {
                if (is_string($key)) {
                    $sanitized[$key] = $value;
                }
            }

            return $sanitized;
        }
    }
} else {
    class LaravelServiceProvider
    {
        public function register(): void
        {
        }

        public function boot(): void
        {
        }
    }
}
