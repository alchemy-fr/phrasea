<?php

namespace Alchemy\RenditionFactory\DTO;

interface OutputFileInterface extends BaseFileInterface
{
    public function createNextInputFile(): InputFileInterface;

    public function getBuildHashes(): ?array;

    public function withBuildHashes(?array $buildHashes): OutputFileInterface;

    public function withProjection(bool $isProjection): OutputFileInterface;

    public function isProjection(): bool;
}
