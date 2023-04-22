<?php

namespace Cnimmo\ListDeps\Util;

use Cnimmo\ListDeps\Singletons\Parser;
use PhpParser\Node;
use PhpParser\NodeFinder;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveRegexIterator;
use RegexIterator;
use SplFileInfo;

class ParsedFileInfo {
    private string $namespace;
    private array $declaredImportableStatementNames;
    private string $filePath;

    public function __construct(string $filePath) {
        $this->filePath = $filePath;
    }

    private function getStatements() {
        return Parser::parse($this->filePath);
    }

    public function getNamespace() {
        if (!isset($this->namespace)) {
            $namespaces = (new NodeFinder)->find($this->getStatements(), function(Node $node) {
                return $node instanceof Node\Stmt\Namespace_;
            });
            $namespaceNames = array_unique(
                array_map(function(Node\Stmt\Namespace_ $namespace) {
                    if (!$namespace->name) {
                        return null;
                    }
                    return $namespace->name->toString();
                }, $namespaces)
            );
            if (count($namespaceNames) > 1) {
                throw new \Exception('Multiple namespaces in file ' . $this->filePath);
            }
            if (count($namespaceNames) === 1) {
                $this->namespace = $namespaceNames[0];
            }
        }
        return $this->namespace ?? null;
    }

    public function getFullyQualifiedNameForStatement(Node $statement) {
        return $this->namespace . '\\' . $statement->name->toString();
    }

    public function getDeclaredImportableStatementNames() {
        if (!isset($this->declaredImportableStatementNames)) {
            $statements = (new NodeFinder)->find($this->getStatements(), function(Node $node) {
                return $node instanceof Node\Stmt\ClassLike
                    || $node instanceof Node\Stmt\Function_
                    || $node instanceof Node\Stmt\Const_;
            });
            $this->declaredImportableStatementNames = array_map(function(Node $statement) {
                return $this->getFullyQualifiedNameForStatement($statement);
            }, $statements);
        }
        return $this->declaredImportableStatementNames;
    }
}

class FileParsingCache {
    /**
     * @var Struct_ParsedFileInfo[]
     */
    private static $allFilesInfo = [];

    public static function init($rootPath, $ignorePaths) {
        $directoryIterator = new RecursiveDirectoryIterator($rootPath);

        $filterIterator = new RecursiveCallbackFilterIterator($directoryIterator, function (SplFileInfo $current) use ($ignorePaths) {
            if ($current->isFile() && $current->getExtension() !== 'php') {
                return false;
            }
            $filePath = $current->getPath() . '/' . $current->getFileName();
            return !in_array($filePath, $ignorePaths);
        });

        $recursiveIterator = new RecursiveIteratorIterator($filterIterator);

        $files = new RegexIterator($recursiveIterator, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);
        
        foreach($files as [$filePath]) {
            self::$allFilesInfo[$filePath] = new ParsedFileInfo($filePath);
        }
    }
    
    public static function getAllNamespaces() {
        return array_values(
            array_unique(
                array_filter(
                    array_map(function($fileInfo) {
                        return $fileInfo->getNamespace();
                    }, self::$allFilesInfo)
                )
            )
        );
    }

    public static function getAllParentNamespaces() {
        return array_values(array_unique(array_filter(array_map(function($fileInfo) {
            if (!$fileInfo->getNamespace()) {
                return null;
            }
            $exploded = explode('\\', $fileInfo->getNamespace());
            return $exploded[count($exploded) - 1];
        }, self::$allFilesInfo))));
    }

    public static function iterate() {
        return self::$allFilesInfo;
    }
}