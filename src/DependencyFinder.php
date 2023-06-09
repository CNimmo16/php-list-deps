<?php

namespace Cnimmo\ListDeps;

use Cnimmo\ListDeps\Singletons\Parser;
use Cnimmo\ListDeps\Util\FileParsingCache;

class DependencyFinder {
    private array $paths;
    private bool $throwOnMissing;
    private string $rootPath;
    
    public function __construct(string | null $rootPath, array $ignorePaths, array $paths, bool $throwOnMissing) {
        $this->paths = $paths;
        $this->throwOnMissing = $throwOnMissing;

        $validatedIgnorePaths = array_map('realpath', $ignorePaths);

        $invalidIgnorePathIndex = array_search(false, $validatedIgnorePaths, true);
        if ($invalidIgnorePathIndex !== false) {
            echo 'Invalid ignore path: ' . $ignorePaths[$invalidIgnorePathIndex] . PHP_EOL;
            exit(1);
        }
        $this->rootPath = $rootPath ?? getcwd();
        FileParsingCache::init($this->rootPath, $validatedIgnorePaths);
    }

    public function findDependencies() {

        $dependentFiles = [];
        foreach ($this->paths as $path) {
            $dependentFiles[$path] = [];
            $dependentFiles[$path] = $this->findDependenciesForFile($path, $this->throwOnMissing, $dependentFiles[$path]);
        }
        return $dependentFiles;
    }

    private array $importsByPath = [];
    private function getImports(string $path) {
        if (!isset($this->importsByPath[$path])) {
            $this->importsByPath[$path] = Parser::getImports($path);
        }
        return $this->importsByPath[$path];
    }

    private array $existence = [];

    private function findFileContainingStatement(string $fullyQualifiedStatementName): string | null {
        if (isset($this->existence[$fullyQualifiedStatementName])) {
            return $this->existence[$fullyQualifiedStatementName];
        }
        $assumedFilePath = realpath($this->rootPath) . '/' . str_replace('\\', '/', $fullyQualifiedStatementName) . '.php';
        foreach (array_keys(FileParsingCache::iterate()) as $filePath) {
            if (strtolower($filePath) === strtolower($assumedFilePath)) {
                $this->existence[$fullyQualifiedStatementName] = $filePath;
                return $filePath;
            }
        }
        foreach (FileParsingCache::iterate() as $filePath => &$info) {
            if ($info->getNamespace() && str_contains($fullyQualifiedStatementName, $info->getNamespace())) {
                foreach ($info->getDeclaredImportableStatementNames() as $statementName) {
                    if ($fullyQualifiedStatementName === $statementName) {
                        $this->existence[$fullyQualifiedStatementName] = $filePath;
                        return $filePath;
                    }
                }
            }
        }
        $this->existence[$fullyQualifiedStatementName] = false;
        return false;
    }

    private function findDependenciesForFile(string $path, bool $throwOnMissing, array &$dependentFiles = []): array {

        $imports = $this->getImports($path);
            
        foreach ($imports as $fullyQualifiedImportName) {
            $filePath = $this->findFileContainingStatement($fullyQualifiedImportName);
            if (!$filePath) {
                if ($throwOnMissing) {
                    echo 'ERROR: Could not find ' . $fullyQualifiedImportName . ' in any file' . PHP_EOL;
                    exit(1);
                }
            } else if (!in_array($filePath, $dependentFiles)) {
                $dependentFiles[] = $filePath;
                $this->findDependenciesForFile($filePath, $throwOnMissing, $dependentFiles);
            }
        }
    
        return $dependentFiles;
    }
    
}
