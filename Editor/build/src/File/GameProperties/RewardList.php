<?php

declare(strict_types=1);

namespace TKG\Mod\File\GameProperties;

use TKG\Mod\Common;
use TKG\Mod\File;
use \RuntimeException;
use \stdClass;

use function \is_string, \pack;

// TODO Refactor around Common\BlobCollector

class RewardList implements Common\IBinaryEncodable {

    private array $aRewards = [];
    private int   $iOffset  = File\Chunk::FIXED_SIZE;

    public function __construct(
        private array $aPlayerAmmoTypes,
        private array $aPlayerSpecialAmmoTypes,
        private Common\StringList $oStringList
    ) {
    }

    public function isEmpty(): bool {
        return empty($this->aRewards);
    }

    public function addReward(stdClass $oData): int {
        if (empty($oData->Description) || !is_string($oData->Description)) {
            throw new RuntimeException('Invalid Reward Structure: Missing Description');
        }
        // Minimal validation.
        $this->validate($oData);

        $iReturnOffset = $this->iOffset;

        $iDescOffset  = $this->oStringList->add($oData->Description);
        $iCurrentSize = 8;

        $aPayload = [
            0,  // Immediate Offset
            0,  // Carry Offset
        ];

        if (isset($oData->Immediate)) {
            $iSize = $this->parsePayload($oData->Immediate, $aPayload);
            $aPayload[0] = $iCurrentSize;
            $iCurrentSize += $iSize;
        }

        if (isset($oData->CarryLimit)) {
            $iSize = $this->parsePayload($oData->CarryLimit, $aPayload);
            $aPayload[1] = $iCurrentSize;
            $iCurrentSize += $iSize;
        }

        // Guarantee 32-bit alignment
        if (count($aPayload) & 1) {
            $aPayload[] = self::MASK_WORD;
            $iCurrentSize += 2;
        }

        $this->iOffset += $iCurrentSize;

        $this->aRewards[$iReturnOffset] = (object)[
            'sDescription' => $oData->Description,
            'iDescOffset'  => $iDescOffset,
            'aPayload'     => $aPayload
        ];

        return $iReturnOffset;
    }

    public function toBinary(): string {
        $sData = '';
        foreach ($this->aRewards as $iOffset => $oData) {
            $sData .= pack('Nn*', $oData->iDescOffset, ...$oData->aPayload);
        }
        return $sData;
    }

    /**
     * Parses the expected JSON structure into the expected set of int16 words,
     * slotting them into the array and terminating.
     */
    private function parsePayload(stdClass $oData, array &$aPayload): int {
        $aPayload[] = (int)($oData->AddHealth ?? 0);
        $aPayload[] = (int)($oData->AddJetpackFuel ?? 0);
        $iSize = 6; // Includes terminator word
        if (isset($oData->AddAmmo)) {
            foreach ($oData->AddAmmo as $sName => $iQuantity) {
                $iAmmoType = $this->aPlayerAmmoTypes[$sName] ??
                    $this->aPlayerSpecialAmmoTypes[$sName] ??
                    throw new RuntimeException('Unknown ammo type: ' . $sName);
                $aPayload[] = $iAmmoType;
                $aPayload[] = $iQuantity;
                $iSize += 4;
            }
        }
        $aPayload[] = self::MASK_WORD;
        return $iSize;
    }

    private function validate(stdClass $oData): void {
        if (empty($oData->Description) || !is_string($oData->Description)) {
            throw new RuntimeException('Invalid Reward: Missing Description');
        }
        $bHasReward = false;
        if (!empty($oData->Immediate)) {
            $this->validateReward($oData->Immediate);
            $bHasReward = true;
        }
        if (!empty($oData->CarryLimit)) {
            $this->validateReward($oData->CarryLimit);
            $bHasReward = true;
        }
        if (!$bHasReward) {
            throw new RuntimeException('Invalid Reward: Missing one of Immediate or CarryLimit');
        }
    }

    private function validateReward(stdClass $oData): void {
        if (
            empty($oData->AddHealth) &&
            empty($oData->AddJetpackFuel) &&
            empty($oData->AddAmmo)
        ) {
            throw new RuntimeException('Invalid Reward: Does not provide any inventory/carry');
        }
    }
}
