<?php
namespace Models;

class Document{
    use LoadFromStd;

    public string $docType;
    public string $fileType;
    public string $name;
    public bool $verified = false;

    public const TYPE_CNI = "cni";
    public const TYPE_RC = "rc";
    public const TYPE_IFU = "ifu";
}
?>