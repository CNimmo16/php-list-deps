<?php

namespace Cnimmo\ListDeps;

use Cnimmo\ListDeps\Util\FileParsingCache;
use Cnimmo\ListDeps\Util\ParsedDoc;
use Cnimmo\ListDeps\Visitors\ImportsVisitor;

class DependencyFinder {
    private array $paths;
    private bool $throwOnMissing;
    
    public function __construct(string $rootPath, array $paths, bool $throwOnMissing) {
        $this->paths = $paths;
        $this->throwOnMissing = $throwOnMissing;

        FileParsingCache::init($rootPath);
    }

    public function findDependencies() {

        $dependentFiles = [];
        foreach ($this->paths as $path) {
            array_push(
                $dependentFiles,
                ...$this->findDependenciesForFile($path, $this->throwOnMissing, $dependentFiles)
            );
        }
        return $dependentFiles;
    }

    private function findDependenciesForFile(string $path, bool $throwOnMissing, array &$dependentFiles = []): array {
        $imports = [];
        $importsVisitor = new ImportsVisitor($imports);
        (new ParsedDoc($path, true))
            ->addVisitors([
                $importsVisitor
            ])
            ->run();
            
        foreach ($imports as $fullyQualifiedImportName) {
            $found = false;
            foreach (FileParsingCache::iterate() as $filePath => &$info) {
                if (str_contains($fullyQualifiedImportName, $info->getNamespace())) {
                    foreach ($info->getDeclaredImportableStatementNames() as $statementName) {
                        if ($fullyQualifiedImportName === $statementName) {
                            if (!in_array($filePath, $dependentFiles)) {
                                $dependentFiles[] = $filePath;
                                $this->findDependenciesForFile($filePath, $throwOnMissing, $dependentFiles);
                            }
                            $found = true;
                            break 2;
                        }
                    }
                }
            }
            if (!$found && $throwOnMissing) {
                throw new \Exception('Could not find ' . $fullyQualifiedImportName);
            }
        }
    
        return $dependentFiles;
    }
    
}
