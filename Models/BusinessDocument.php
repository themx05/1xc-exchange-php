<?php
namespace Models;
class BusinessDocument{
    use LoadFromStd;
    public string $docType;
    public string $fileType;
    public string $name;

    public const DOCTYPE_CNI = "cni";
    public const DOCTYPE_RC = "rc";
    public const DOCTYPE_IFU = "ifu";

}
?>
