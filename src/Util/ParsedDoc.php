<?php

namespace Cnimmo\ListDeps\Util;

use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;

class ParsedDoc {

    private string $filePath;
    private NodeTraverser $traverser;
    private Parser $parser;
    public array $stmts;

    public function __construct(string $filePath, bool $withQualifiedNames) {
        $this->filePath = $filePath;

        $this->parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);

        $this->traverser = new NodeTraverser;
        if ($withQualifiedNames) {
            $nameResolver = new NameResolver(null, [
                'preserveOriginalNames' => true,
            ]);
            $this->traverser->addVisitor($nameResolver);
        }

        return $this;
    }

    public function addVisitors(array $visitors) {

        foreach ($visitors as $visitor) {
            $this->traverser->addVisitor($visitor);
        }

        return $this;
    }

    public function run() {
        $this->stmts = $this->parser->parse(file_get_contents($this->filePath));
        $this->traverser->traverse($this->stmts);

        return $this;
    }
}
