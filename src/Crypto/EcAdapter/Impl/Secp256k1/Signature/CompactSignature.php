<?php

namespace BitWasp\Bitcoin\Crypto\EcAdapter\Impl\Secp256k1\Signature;

use BitWasp\Bitcoin\Crypto\EcAdapter\Impl\Secp256k1\Adapter\EcAdapter;
use BitWasp\Bitcoin\Crypto\EcAdapter\Impl\Secp256k1\Serializer\Signature\CompactSignatureSerializer;
use BitWasp\Buffertools\BufferInterface;

class CompactSignature extends Signature implements CompactSignatureInterface
{
    /**
     * @var resource
     */
    private $resource;

    /**
     * @var bool
     */
    private $compressed;

    /**
     * @var int
     */
    private $recid;

    /**
     * @var EcAdapter
     */
    private $ecAdapter;

    /**
     * @param EcAdapter $ecAdapter
     * @param resource $secp256k1_ecdsa_signature_t
     * @param int $recid
     * @param bool $compressed
     */
    public function __construct(EcAdapter $ecAdapter, $secp256k1_ecdsa_signature_t, $recid, $compressed)
    {
        $math = $ecAdapter->getMath();
        if (!is_bool($compressed)) {
            throw new \InvalidArgumentException('CompactSignature: compressed must be a boolean');
        }

        if (!is_resource($secp256k1_ecdsa_signature_t)
            || SECP256K1_TYPE_RECOVERABLE_SIG !== get_resource_type($secp256k1_ecdsa_signature_t)
        ) {
            throw new \RuntimeException('CompactSignature: must pass recoverable signature resource');
        }

        $ser = '';
        $recidout = '';
        secp256k1_ecdsa_recoverable_signature_serialize_compact($ecAdapter->getContext(), $secp256k1_ecdsa_signature_t, $ser, $recidout);
        list ($r, $s) = array_map(
            function ($val) use ($math) {
                return gmp_init($math->hexDec(bin2hex($val)), 10);
            },
            str_split($ser, 32)
        );

        $this->resource = $secp256k1_ecdsa_signature_t;
        $this->recid = $recid;
        $this->compressed = $compressed;
        $this->ecAdapter = $ecAdapter;
        parent::__construct($ecAdapter, $r, $s, $secp256k1_ecdsa_signature_t);
    }

    /**
     * @return Signature
     */
    public function convert()
    {
        $sig_t = '';
        /** @var resource $sig_t */
        secp256k1_ecdsa_recoverable_signature_convert($this->ecAdapter->getContext(), $sig_t, $this->resource);
        return new Signature($this->ecAdapter, $this->getR(), $this->getS(), $sig_t);
    }

    /**
     * @return resource
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * @return int
     */
    public function getRecoveryId()
    {
        return $this->recid;
    }

    /**
     * @return int|string
     */
    public function getFlags()
    {
        return $this->getRecoveryId() + 27 + ($this->isCompressed() ? 4 : 0);
    }

    /**
     * @return bool
     */
    public function isCompressed()
    {
        return $this->compressed;
    }

    /**
     * @return BufferInterface
     */
    public function getBuffer()
    {
        return (new CompactSignatureSerializer($this->ecAdapter))->serialize($this);
    }
}
