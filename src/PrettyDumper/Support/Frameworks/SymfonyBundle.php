<?php

declare(strict_types=1);

namespace Anhoder\PrettyDumper\Support\Frameworks;

use Anhoder\PrettyDumper\Formatter\DumpRenderRequest;
use Anhoder\PrettyDumper\Formatter\FormatterConfiguration;
use Anhoder\PrettyDumper\Formatter\PrettyFormatter;
use Anhoder\PrettyDumper\Renderer\CliRenderer;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\Bundle;

if (class_exists(Bundle::class)) {
    class SymfonyBundle extends Bundle
    {
        public function build(mixed $container): void
        {
            if (!$container instanceof ContainerBuilder) {
                return;
            }

            if (!$container->hasParameter('pretty_dumper.config')) {
                $container->setParameter('pretty_dumper.config', []);
            }

            $container
                ->register('pretty_dumper.configuration', FormatterConfiguration::class)
                ->setFactory([self::class, 'createConfiguration'])
                ->addArgument('%pretty_dumper.config%');

            $container
                ->register('pretty_dumper.formatter', PrettyFormatter::class)
                ->setFactory([self::class, 'createFormatter'])
                ->addArgument(new Reference('pretty_dumper.configuration'));

            $container
                ->register('pretty_dumper.cli_renderer', CliRenderer::class)
                ->setFactory([self::class, 'createCliRenderer'])
                ->addArgument(new Reference('pretty_dumper.formatter'));
        }

        /**
         * @param array<string, mixed> $options
         */
        public static function createConfiguration(array $options = []): FormatterConfiguration
        {
            return new FormatterConfiguration($options);
        }

        public static function createFormatter(FormatterConfiguration $configuration): PrettyFormatter
        {
            return PrettyFormatter::forChannel('cli', $configuration);
        }

        public static function createCliRenderer(PrettyFormatter $formatter): CliRenderer
        {
            return new CliRenderer($formatter);
        }

        /**
         * @param array<string, mixed> $options
         */
        public static function dump(mixed $value, array $options = []): string
        {
            $formatter = PrettyFormatter::forChannel('cli', new FormatterConfiguration($options));
            $renderer = new CliRenderer($formatter);
            $request = new DumpRenderRequest($value, 'cli', $options);

            return $renderer->render($request);
        }
    }
} else {
    class SymfonyBundle
    {
        public function build(mixed $container): void
        {
        }
    }
}
