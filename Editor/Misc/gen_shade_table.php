<?php

declare(strict_types=1);

/**
 * Simple RGB Tuple, using normalised channels (0.0 - 1.0)
 */
final class RGBVal {
    public float $fRed;
    public float $fGreen;
    public float $fBlue;

    public function __construct(float $fRed, float $fGreen, float $fBlue) {
        $this->fRed   = $fRed;
        $this->fGreen = $fGreen;
        $this->fBlue  = $fBlue;
    }

    public function blendSquare(self $oOther, float $fWeight = 0.5): self {
        $fOneMinusWeight = 1.0 - $fWeight;
        $this->fRed = sqrt(
            $fOneMinusWeight * $this->fRed * $this->fRed +
            $fWeight         * $oOther->fRed * $oOther->fRed
        );
        $this->fGreen = sqrt(
            $fOneMinusWeight * $this->fGreen * $this->fGreen +
            $fWeight         * $oOther->fGreen * $oOther->fGreen
        );
        $this->fBlue = sqrt(
            $fOneMinusWeight * $this->fBlue * $this->fBlue +
            $fWeight         * $oOther->fBlue * $oOther->fBlue
        );
        return $this;
    }

    public function blendLinear(self $oOther, float $fWeight = 0.5): self {
        $fOneMinusWeight = 1.0 - $fWeight;
        $this->fRed =
            $fOneMinusWeight * $this->fRed +
            $fWeight         * $oOther->fRed;
        $this->fGreen = 
            $fOneMinusWeight * $this->fGreen +
            $fWeight         * $oOther->fGreen;
        $this->fBlue =
            $fOneMinusWeight * $this->fBlue +
            $fWeight         * $oOther->fBlue;
        return $this;
    }

    public function quantise(int $iMax): array {
        $iRed   = (int)min($iMax * $this->fRed, $iMax);
        $iGreen = (int)min($iMax * $this->fGreen, $iMax);
        $iBlue  = (int)min($iMax * $this->fBlue, $iMax);
        return [$iRed, $iGreen, $iBlue];
    }


}

final class LABVal {
    public float $fL;
    public float $fA;
    public float $fB;

    public function __construct(float $fL, float $fA, float $fB) {
        $this->fL = $fL;
        $this->fA = $fA;
        $this->fB = $fB;
    } 
}

class OKLAB {

    private const ONE_THIRD = 1.0/3.0;

    public static function convRGBToLAB(RGBVal $oRGB): LABVal {
        $fL = 0.4122214708 * $oRGB->fRed + 0.5363325363 * $oRGB->fGreen + 0.0514459929 * $oRGB->fBlue;
        $fM = 0.2119034982 * $oRGB->fRed + 0.6806995451 * $oRGB->fGreen + 0.1073969566 * $oRGB->fBlue;
        $fS = 0.0883024619 * $oRGB->fRed + 0.2817188376 * $oRGB->fGreen + 0.6299787005 * $oRGB->fBlue;

        $fLCubeRoot = $fL ** self::ONE_THIRD;
        $fMCubeRoot = $fM ** self::ONE_THIRD;
        $fSCubeRoot = $fS ** self::ONE_THIRD;

        return new LABVal(
            0.2104542553 * $fLCubeRoot + 0.7936177850 * $fMCubeRoot - 0.0040720468 * $fSCubeRoot,
            1.9779984951 * $fLCubeRoot - 2.4285922050 * $fMCubeRoot + 0.4505937099 * $fSCubeRoot,
            0.0259040371 * $fLCubeRoot + 0.7827717662 * $fMCubeRoot - 0.8086757660 * $fSCubeRoot
        );
    }

    public static function convLABToRGB(LABVal $oLAB): RGBVal {
        $fLCubeRoot = $oLAB->fL + 0.3963377774 * $oLAB->fA + 0.2158037573 * $oLAB->fB;
        $fMCubeRoot = $oLAB->fL - 0.1055613458 * $oLAB->fA - 0.0638541728 * $oLAB->fB;
        $fSCubeRoot = $oLAB->fL - 0.0894841775 * $oLAB->fA - 1.2914855480 * $oLAB->fB;

        $fL = $fLCubeRoot ** 3;
        $fM = $fMCubeRoot ** 3;
        $fS = $fSCubeRoot ** 3;

        return new RGBVal(
		    +4.0767416621 * $fL - 3.3077115913 * $fM + 0.2309699292 * $fS,
		    -1.2684380046 * $fL + 2.6097574011 * $fM - 0.3413193965 * $fS,
		    -0.0041960863 * $fL - 0.7034186147 * $fM + 1.7076147010 * $fS
        );
    }
}


/**
 * Simple HSV Tuple, using degree Hue and normalized sat/value
 */
final class HSVVal {
    public float $fHue;
    public float $fSaturation;
    public float $fValue;

    public function __construct(float $fHue, float $fSaturation, float $fValue) {
        $this->fHue        = $fHue;
        $this->fSaturation = $fSaturation;
        $this->fValue      = $fValue;
    }

    public static function from(RGBVal $oRGB): self {

        $fCMax  = max($oRGB->fRed, max($oRGB->fGreen, $oRGB->fBlue));
        $fCMin  = min($oRGB->fRed, min($oRGB->fGreen, $oRGB->fBlue));
        $fCDiff = $fCMax - $fCMin;
        $fHue   = -1.0;
        $fSat   = -1.0;

        if (self::equals($fCDiff, 0.0)) {
            $fHue = 0.0;
        } else if (self::equals($fCMax, $oRGB->fRed)) {
            $fHue = fmod(60.0 * (($oRGB->fGreen - $oRGB->fBlue) / $fCDiff) + 360.0, 360.0);
        } else if (self::equals($fCMax, $oRGB->fGreen)) {
            $fHue = fmod(60.0 * (($oRGB->fBlue - $oRGB->fRed) / $fCDiff) + 120.0, 360.0);
        } else if (self::equals($fCMax, $oRGB->fBlue)) {
            $fHue = fmod(60.0 * (($oRGB->fRed - $oRGB->fGreen) / $fCDiff) + 240.0, 360.0);
        }

        if (self::equals($fCMax, 0.0)) {
            $fSat = 0.0;
        } else {
            $fSat = $fCDiff/$fCMax;
        }
        return new self(
            $fHue, $fSat, $fCMax
        );
    }



    public function toRGB(): RGBVal {
        // Greyscale has zero saturation.
        if (self::equals($this->fSaturation, 0.0)) {
            return new RGBVal($this->fValue, $this->fValue, $this->fValue);
        }

        $fHue  = $this->fHue;
        $fHue  /= 60.0;
        $iHue  = (int)$fHue;
        $fFrac = $fHue - (float)$iHue;

        $fP    = $this->fValue * (1.0 - $this->fSaturation);
        $fQ    = $this->fValue * (1.0 - ($this->fSaturation * $fFrac));
        $fT    = $this->fValue * (1.0 - ($this->fSaturation * (1.0 - $fFrac)));

        switch ($iHue) {
            case 0: return new RGBVal($this->fValue, $fT, $fP);
            case 1: return new RGBVal($fQ, $this->fValue, $fP);
            case 2: return new RGBVal($fP, $this->fValue, $fT);
            case 3: return new RGBVal($fP, $fQ, $this->fValue);
            case 4: return new RGBVal($fT, $fP, $this->fValue);
            case 5: return new RGBVal($this->fValue, $fP, $fQ);
        }
        throw new RangeException('Unexpected integer hue selector ' . $iHue);
    }

    private static function equals(float $fA, float $fB): bool {
        return (abs($fA - $fB) <= PHP_FLOAT_EPSILON);
    }
}

/**
 * Palette class, vector of RGBVal
 */
class Palette {

    private const F_NORM = 1.0/255.0;
    private SPLFixedArray $oEntriesNormalised;
    private SPLFixedArray $oEntriesLAB;

    public function __construct(string $sSource) {
        if (!is_readable($sSource)) {
            throw new RuntimeException();
        }
        $aData = unpack('n768', file_get_contents($sSource));

        $this->oEntriesNormalised = new SPLFixedArray(256);
        $this->oEntriesLAB        = new SPLFixedArray(256);
        $j = 0;
        for ($i=1; $i <= 768; $i+=3) {
            $oRGB = new RGBVal(
                self::F_NORM * $aData[$i],
                self::F_NORM * $aData[$i+1],
                self::F_NORM * $aData[$i+2]
            );
            $this->oEntriesNormalised[$j] = $oRGB;
            $this->oEntriesLAB[$j] = OKLAB::convRGBToLAB($oRGB);
            ++$j;
        }
    }

    /**
     * Find the closest matching index for an input RGB
     */
    public function match(RGBVal $oRGB): int {
        $oLAB = OKLAB::convRGBToLAB($oRGB);
        $iMatch = -1;
        $fBest  = 1e20;
        foreach ($this->oEntriesLAB as $iIndex => $oPalLAB) {

            $fDeltaL = $oPalLAB->fL - $oLAB->fL;
            $fDeltaA = $oPalLAB->fA - $oLAB->fA;
            $fDeltaB = $oPalLAB->fB - $oLAB->fB;

            $fDistanceSquared = (
                $fDeltaL * $fDeltaL +
                $fDeltaA * $fDeltaA +
                $fDeltaB * $fDeltaB
            );


            if ($fDistanceSquared < $fBest) {
                $fBest  = $fDistanceSquared;
                $iMatch = $iIndex;
            }

        }
        return $iMatch;
    }

    public function getIndex(int $i): RGBVal {
        return $this->oEntriesNormalised[$i];
    }
}



class ShadeTable {
    private Palette $oPalette;

    public function __construct(Palette $oPalette) {
        $this->oPalette = $oPalette;
    }

    public function generate(string $sPath, float $fGlarePower = 1.0, float $fDarkenPower = 1.0) {
        $rRGBOutput = fopen($sPath . '_rgb.ppm', 'w');
        $r256Output = fopen($sPath . '_256.ppm', 'w');
        $rRawOutput = fopen($sPath . '_raw.pgm', 'w');

        fwrite($rRGBOutput, sprintf("P6\n256 64\n255\n"));
        fwrite($r256Output, sprintf("P6\n256 64\n255\n"));
        fwrite($rRawOutput, sprintf("P5\n256 64\n255\n"));

        for ($b = 0; $b < 32; ++$b) {
            $fIntensity = ($b / 32.0) ** $fGlarePower;
            for ($i = 0; $i < 256; ++$i) {
                $oHSV = HSVVal::from($this->oPalette->getIndex($i));
                $oHSV->fSaturation *= $fIntensity;
                $oHSV->fValue += (1.0 - $fIntensity);
                $oRGB = $oHSV->toRGB();

                $aRGB = $oRGB->quantise(255);
                fwrite($rRGBOutput, chr($aRGB[0]) . chr($aRGB[1]) . chr($aRGB[2]));

                $iMatched = $this->oPalette->match($oRGB);
                fwrite($rRawOutput, chr($iMatched));

                $aRGB = $this->oPalette->getIndex($iMatched)->quantise(255);
                fwrite($r256Output, chr($aRGB[0]) . chr($aRGB[1]) . chr($aRGB[2]));
            }
        }

        // Row 32 is the unmodified palette.
        for ($i = 0; $i < 256; ++$i) {
            fwrite($rRawOutput, chr($i));
            $aRGB = $this->oPalette->getIndex($i)->quantise(255);
            fwrite($r256Output, chr($aRGB[0]) . chr($aRGB[1]) . chr($aRGB[2]));
            fwrite($rRGBOutput, chr($aRGB[0]) . chr($aRGB[1]) . chr($aRGB[2]));
        }

        for ($b = 1; $b < 32; ++$b) {
            $fIntensity = ((31 - $b) / 31.0) ** $fDarkenPower;
            for ($i = 0; $i < 256; ++$i) {
                $oHSV = HSVVal::from($this->oPalette->getIndex($i));
                $oHSV->fValue *= $fIntensity;
                $oRGB = $oHSV->toRGB();
                $aRGB = $oRGB->quantise(255);
                fwrite($rRGBOutput, chr($aRGB[0]) . chr($aRGB[1]) . chr($aRGB[2]));

                $iMatched = $this->oPalette->match($oRGB);
                fwrite($rRawOutput, chr($iMatched));

                $aRGB = $this->oPalette->getIndex($iMatched)->quantise(255);
                fwrite($r256Output, chr($aRGB[0]) . chr($aRGB[1]) . chr($aRGB[2]));
            }
        }

        fclose($rRGBOutput);
        fclose($r256Output);
        fclose($rRawOutput);

        system('gopen ' . $sPath . '_raw.pgm');
        system('gopen ' . $sPath . '_rgb.ppm');
        system('gopen ' . $sPath . '_256.ppm');

    }
}


(new ShadeTable(new Palette('256PAL')))->generate('shade_table', 0.8, 1.2);


