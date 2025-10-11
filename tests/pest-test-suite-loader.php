<?php

declare(strict_types=1);

namespace Pest;

use PHPUnit\Runner\TestSuiteLoader as PhpUnitTestSuiteLoader;
use ReflectionClass;

if (!class_exists(TestSuiteLoader::class)) {
    class TestSuiteLoader implements PhpUnitTestSuiteLoader
    {
        /**
         * @return ReflectionClass<TestSuite>
         */
        public function load(string $suiteClassFile): ReflectionClass
        {
            $this->requireFile($suiteClassFile);

            return new ReflectionClass(TestSuite::class);
        }

        /**
         * @param ReflectionClass<TestSuite> $aClass
         * @return ReflectionClass<TestSuite>
         */
        public function reload(ReflectionClass $aClass): ReflectionClass
        {
            return $aClass;
        }

        private function requireFile(string $file): void
        {
            if ($file === '' || !is_file($file)) {
                return;
            }

            /**
             * @psalm-suppress UnresolvableInclude
             */
            require_once $file;
        }
    }
}
