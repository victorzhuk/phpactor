<?php

namespace Phpactor\WorseReflection\Bridge\Phpactor\DocblockParser;

use Phpactor\DocblockParser\Ast\Type\ArrayShapeNode;
use Phpactor\DocblockParser\Ast\Type\LiteralFloatNode;
use Phpactor\DocblockParser\Ast\Type\LiteralIntegerNode;
use Phpactor\DocblockParser\Ast\Type\LiteralStringNode;
use Phpactor\DocblockParser\Ast\Type\ParenthesizedType;
use Phpactor\WorseReflection\Core\Type\ArrayKeyType;
use Phpactor\DocblockParser\Ast\Node;
use Phpactor\DocblockParser\Ast\TypeNode;
use Phpactor\DocblockParser\Ast\Type\ArrayNode;
use Phpactor\DocblockParser\Ast\Type\CallableNode;
use Phpactor\DocblockParser\Ast\Type\ClassNode;
use Phpactor\DocblockParser\Ast\Type\GenericNode;
use Phpactor\DocblockParser\Ast\Type\ListNode;
use Phpactor\DocblockParser\Ast\Type\NullNode;
use Phpactor\DocblockParser\Ast\Type\ScalarNode;
use Phpactor\DocblockParser\Ast\Type\ThisNode;
use Phpactor\DocblockParser\Ast\Type\UnionNode;
use Phpactor\WorseReflection\Core\ClassName;
use Phpactor\WorseReflection\Core\Reflection\ReflectionScope;
use Phpactor\WorseReflection\Core\Type;
use Phpactor\WorseReflection\Core\Type\ArrayShapeType;
use Phpactor\WorseReflection\Core\Type\ArrayType;
use Phpactor\WorseReflection\Core\Type\BooleanType;
use Phpactor\WorseReflection\Core\Type\CallableType;
use Phpactor\WorseReflection\Core\Type\ClassStringType;
use Phpactor\WorseReflection\Core\Type\ClassType;
use Phpactor\WorseReflection\Core\Type\ClosureType;
use Phpactor\WorseReflection\Core\Type\FloatLiteralType;
use Phpactor\WorseReflection\Core\Type\FloatType;
use Phpactor\WorseReflection\Core\Type\GenericClassType;
use Phpactor\WorseReflection\Core\Type\IntLiteralType;
use Phpactor\WorseReflection\Core\Type\IntType;
use Phpactor\WorseReflection\Core\Type\IterablePrimitiveType;
use Phpactor\WorseReflection\Core\Type\MissingType;
use Phpactor\WorseReflection\Core\Type\MixedType;
use Phpactor\WorseReflection\Core\Type\NullType;
use Phpactor\WorseReflection\Core\Type\ObjectType;
use Phpactor\WorseReflection\Core\Type\ParenthesizedType as PhpactorParenthesizedType;
use Phpactor\WorseReflection\Core\Type\ReflectedClassType;
use Phpactor\WorseReflection\Core\Type\ResourceType;
use Phpactor\WorseReflection\Core\Type\SelfType;
use Phpactor\WorseReflection\Core\Type\StaticType;
use Phpactor\WorseReflection\Core\Type\StringLiteralType;
use Phpactor\WorseReflection\Core\Type\StringType;
use Phpactor\WorseReflection\Core\Type\UnionType;
use Phpactor\WorseReflection\Core\Type\VoidType;
use Phpactor\WorseReflection\Reflector;

class TypeConverter
{
    private Reflector $reflector;

    public function __construct(Reflector $reflector)
    {
        $this->reflector = $reflector;
    }

    public function convert(?TypeNode $type, ?ReflectionScope $scope = null): Type
    {
        if ($type instanceof ScalarNode) {
            return $this->convertScalar($type->toString());
        }
        if ($type instanceof ListNode) {
            return $this->convertList($type, $scope);
        }
        if ($type instanceof ArrayNode) {
            return $this->convertArray($type);
        }
        if ($type instanceof ArrayShapeNode) {
            return $this->convertArrayShape($type);
        }
        if ($type instanceof UnionNode) {
            return $this->convertUnion($type);
        }
        if ($type instanceof GenericNode) {
            return $this->convertGeneric($type, $scope);
        }
        if ($type instanceof ClassNode) {
            return $this->convertClass($type, $scope);
        }
        if ($type instanceof ThisNode) {
            return $this->convertThis($type);
        }
        if ($type instanceof NullNode) {
            return new NullType();
        }

        if ($type instanceof CallableNode) {
            return $this->convertCallable($type, $scope);
        }

        if ($type instanceof ParenthesizedType) {
            return $this->convertParenthesized($type, $scope);
        }
        if ($type instanceof LiteralStringNode) {
            return $this->convertLiteralString($type, $scope);
        }
        if ($type instanceof LiteralIntegerNode) {
            return $this->convertLiteralInteger($type, $scope);
        }
        if ($type instanceof LiteralFloatNode) {
            return $this->convertLiteralFloat($type, $scope);
        }

        return new MissingType();
    }

    private function convertScalar(string $type): Type
    {
        if ($type === 'int') {
            return new IntType();
        }
        if ($type === 'string') {
            return new StringType();
        }
        if ($type === 'class-string') {
            return new ClassStringType();
        }
        if ($type === 'float') {
            return new FloatType();
        }
        if ($type === 'mixed') {
            return new MixedType();
        }
        if ($type === 'bool') {
            return new BooleanType();
        }
        if ($type === 'callable') {
            return new CallableType([], new MissingType());
        }

        return new MissingType();
    }

    private function convertArray(ArrayNode $type): Type
    {
        return new ArrayType(new ArrayKeyType(), new MissingType());
    }

    private function convertUnion(UnionNode $union): Type
    {
        return new UnionType(...array_map(
            fn (Node $node) => $this->convert($node),
            iterator_to_array($union->types->types())
        ));
    }

    private function convertGeneric(GenericNode $type, ?ReflectionScope $scope): Type
    {
        if ($type->type instanceof ArrayNode) {
            $parameters = array_values(iterator_to_array($type->parameters()->types()));
            if (count($parameters) === 1) {
                return new ArrayType(
                    new ArrayKeyType(),
                    $this->convert($parameters[0], $scope)
                );
            }
            if (count($parameters) === 2) {
                return new ArrayType(
                    $this->convert($parameters[0], $scope),
                    $this->convert($parameters[1], $scope),
                );
            }
            return new MissingType();
        }

        $classType = $this->convert($type->type, $scope);

        if (!$classType instanceof ClassType) {
            return new MissingType();
        }

        $parameters = iterator_to_array($type->parameters()->types());

        return new GenericClassType(
            $this->reflector,
            $classType->name(),
            array_map(
                fn (TypeNode $node) => $this->convert($node, $scope),
                $parameters
            )
        );
    }

    private function convertClass(ClassNode $typeNode, ?ReflectionScope $scope): Type
    {
        $name = $typeNode->name()->toString();

        if ($name === 'static') {
            return new StaticType();
        }

        if ($name === 'iterable') {
            return new IterablePrimitiveType();
        }

        if ($name === 'self') {
            return new SelfType();
        }

        if ($name === 'object') {
            return new ObjectType();
        }

        if ($name === 'resource') {
            return new ResourceType();
        }

        if ($name === 'void') {
            return new VoidType();
        }

        $type = new ReflectedClassType(
            $this->reflector,
            ClassName::fromString(
                $typeNode->name()->toString()
            )
        );

        if ($scope) {
            return $scope->resolveFullyQualifiedName($type);
        }

        return $type;
    }

    private function convertList(ListNode $type, ?ReflectionScope $scope): Type
    {
        return new ArrayType($this->convert($type->type, $scope));
    }

    private function convertThis(ThisNode $type): Type
    {
        return new SelfType();
    }

    private function convertCallable(CallableNode $callableNode, ?ReflectionScope $scope): CallableType
    {
        $parameters = array_map(function (TypeNode $type) {
            return $this->convert($type);
        }, $callableNode->parameters ? iterator_to_array($callableNode->parameters->types()) : []);

        $type = $this->convert($callableNode->type);

        if ($callableNode->name && $callableNode->name->toString() === 'Closure') {
            return new ClosureType($parameters, $type);
        }

        return new CallableType($parameters, $type);
    }

    private function convertArrayShape(ArrayShapeNode $type): ArrayShapeType
    {
        $typeMap = [];
        foreach (array_values($type->arrayKeyValueList->arrayKeyValues()) as $index => $keyValue) {
            $key = $keyValue->key ? $keyValue->key->value : $index;
            $typeMap[$key] = $this->convert($keyValue->type);
        }

        return new ArrayShapeType($typeMap);
    }

    private function convertParenthesized(ParenthesizedType $type, ?ReflectionScope $scope): Type
    {
        return new PhpactorParenthesizedType($this->convert($type->node));
    }

    private function convertLiteralString(LiteralStringNode $type, ?ReflectionScope $scope): Type
    {
        $quote = substr($type->token->value, 0, 1);
        $string = trim($type->token->value, $quote);

        return new StringLiteralType($string);
    }

    private function convertLiteralInteger(LiteralIntegerNode $type, ?ReflectionScope $scope): Type
    {
        return new IntLiteralType((int)$type->token->value);
    }

    private function convertLiteralFloat(LiteralFloatNode $type, ?ReflectionScope $scope): Type
    {
        return new FloatLiteralType((float)$type->token->value);
    }
}
