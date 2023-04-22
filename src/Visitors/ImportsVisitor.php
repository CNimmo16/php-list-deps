<?php

namespace Cnimmo\ListDeps\Visitors;

use Cnimmo\ListDeps\Util\FileParsingCache;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class ImportsVisitor extends NodeVisitorAbstract
{
    private array $allNamespaces;
    private array $res;

    public function __construct(&$res)
    {
        $this->res = &$res;
        $this->allNamespaces = FileParsingCache::getAllNamespaces();
    }

    public function handleName(Node\Name $name)
    {
        if ($name instanceof Node\Name\Relative) {
            throw new \Exception('No relatives allowed');
        } else {
            $fullName = implode('\\', $name->parts);
            $namespaceName = implode('\\', array_slice($name->parts, 0, count($name->parts) - 1));
            if (in_array($namespaceName, $this->allNamespaces)) {
                $this->res[] = $fullName;
            }
        }
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\UseUse) {
            // use is always fully qualified
            $this->handleName($node->name);
        } else if ($node instanceof Node\Name\FullyQualified || $node instanceof Node\Name\Relative) {
            $this->handleName($node);
        }
    }

    public function afterTraverse(array $nodes)
    {
        $this->res = array_unique($this->res);
    }
}