<?php

namespace Cnimmo\ListDeps\Singletons;

use Cnimmo\ListDeps\Visitors\ImportsVisitor;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser as PhpParser;
use PhpParser\ParserFactory;

class Parser {

    private static PhpParser $parser;

    private static function getParserInstance(): PhpParser {
        if (!isset(self::$parser)) {
            self::$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        }
        return self::$parser;
    }

    private static function getTraverserInstance(&$visitors) {
        $traverser = new NodeTraverser();

        foreach ($visitors as $visitor) {
            $traverser->addVisitor($visitor);
        }

        return $traverser;
    }

    private static NodeTraverser $importsTraverser;
    private static array $imports = [];

    public static function getImportsTraverser() {
        if (!isset(self::$importsTraverser)) {
            $visitors = [
                new NameResolver(),
                new ImportsVisitor(self::$imports)
            ];
            self::$importsTraverser = self::getTraverserInstance($visitors);
        }
        return self::$importsTraverser;
    }

    public static function parse(string $filePath) {
        $contents = file_get_contents($filePath);
        return self::getParserInstance()->parse($contents);
    }

    public static function getImports(string $filePath) {
        $traverser = self::getImportsTraverser();
        $traverser->traverse(self::parse($filePath));
        return self::$imports;
    }
}
