<?php

namespace BitWasp\Bitcoin\Crypto\EcAdapter\Impl\Secp256k1\Serializer\Signature;

use BitWasp\Bitcoin\Crypto\EcAdapter\Adapter\EcAdapterInterface;
use BitWasp\Bitcoin\Crypto\EcAdapter\Impl\Secp256k1\Adapter\EcAdapter;
use BitWasp\Bitcoin\Crypto\EcAdapter\Impl\Secp256k1\Signature\Signature;
use BitWasp\Bitcoin\Crypto\EcAdapter\Serializer\Signature\DerSignatureSerializerInterface;
use BitWasp\Bitcoin\Crypto\EcAdapter\Signature\SignatureInterface;
use BitWasp\Buffertools\Buffer;
use BitWasp\Buffertools\Parser;
use BitWasp\Buffertools\Template;
use BitWasp\Buffertools\TemplateFactory;

class DerSignatureSerializer implements DerSignatureSerializerInterface
{
    /**
     * @var EcAdapter
     */
    private $ecAdapter;

    /**
     * @param EcAdapter $ecAdapter
     */
    public function __construct(EcAdapter $ecAdapter)
    {
        $this->ecAdapter = $ecAdapter;
    }

    /**
     * @return EcAdapterInterface
     */
    public function getEcAdapter()
    {
        return $this->ecAdapter;
    }

    /**
     * @param Signature $signature
     * @return Buffer
     */
    private function doSerialize(Signature $signature)
    {
        $signatureOut = '';
        if (!secp256k1_ecdsa_signature_serialize_der($this->ecAdapter->getContext(), $signatureOut, $signature->getResource())) {
            throw new \RuntimeException('Secp256k1: serialize der failure');
        }

        return new Buffer($signatureOut);
    }

    /**
     * @param SignatureInterface $signature
     * @return Buffer
     */
    public function serialize(SignatureInterface $signature)
    {
        /** @var Signature $signature */
        return $this->doSerialize($signature);
    }

    /**
     * @return Template
     */
    private function getInnerTemplate()
    {
        return (new TemplateFactory())
            ->uint8()
            ->varstring()
            ->uint8()
            ->varstring()
            ->getTemplate();
    }

    /**
     * @return Template
     */
    private function getOuterTemplate()
    {
        return (new TemplateFactory())
            ->uint8()
            ->varstring()
            ->getTemplate();
    }

    /**
     * @param $data
     * @return SignatureInterface
     */
    public function parse($data)
    {
        $buffer = (new Parser($data))->getBuffer();
        $binary = $buffer->getBinary();

        $sig_t = '';
        /** @var resource $sig_t */
        if (!secp256k1_ecdsa_signature_parse_der($this->ecAdapter->getContext(), $sig_t, $binary)) {
            throw new \RuntimeException('Secp256k1: parse der failure');
        }

        // Unfortunately, we need to use the Parser here to get r and s :/
        list (, $inner) = $this->getOuterTemplate()->parse(new Parser($buffer));
        list (, $r, , $s) = $this->getInnerTemplate()->parse(new Parser($inner));
        /** @var Buffer $r */
        /** @var Buffer $s */

        return new Signature($this->ecAdapter, gmp_init($r->getInt(), 10), gmp_init($s->getInt(), 10), $sig_t);
    }
}
