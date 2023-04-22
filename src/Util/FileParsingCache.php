<?php

namespace Cnimmo\ListDeps\Util;

use Cnimmo\ListDeps\Util\ParsedDoc;
use PhpParser\Node;
use PhpParser\NodeFinder;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveRegexIterator;
use RegexIterator;

class Struct_ParsedFileInfo {
    private string $namespace;
    private array $declaredImportableStatementNames;
    private string $filePath;

    public function __construct(string $filePath) {
        $this->filePath = $filePath;
    }

    private function getStmts() {
        return (new ParsedDoc($this->filePath, false))->run()->stmts;
    }

    public function getNamespace() {
        if (!isset($this->namespace)) {
            [$namespace] = (new NodeFinder)->find($this->getStmts(), function(Node $node) {
                return $node instanceof Node\Stmt\Namespace_;
            });
            $this->namespace = $namespace->name->toString();
        }
        return $this->namespace;
    }

    public function getFullyQualifiedNameForStatement(Node $statement) {
        return $this->namespace . '\\' . $statement->name->toString();
    }

    public function getDeclaredImportableStatementNames() {
        if (!isset($this->declaredImportableStatementNames)) {
            $statements = (new NodeFinder)->find($this->getStmts(), function(Node $node) {
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

    public static function init($rootPath) {
        $directoryIterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootPath)
        );
        $fileIterator = new RegexIterator($directoryIterator, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);
        
        foreach($fileIterator as [$filePath]) {
            self::$allFilesInfo[$filePath] = new Struct_ParsedFileInfo($filePath);
        }
    }

    public static function getAllNamespaces() {
        return array_map(function($fileInfo) {
            return $fileInfo->getNamespace();
        }, self::$allFilesInfo);
    }

    public static function iterate() {
        return self::$allFilesInfo;
    }
}