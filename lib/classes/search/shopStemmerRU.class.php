<?php

class shopStemmerRU
{
    const PERFECTIVEGROUND = '/((ив|ивши|ившись|ыв|ывши|ывшись)|((?<=[ая])(в|вши|вшись)))$/';
    const REFLEXIVE = '/(с[яь])$/';
    const ADJECTIVE = '/(ее|ие|ые|ое|ими|ыми|ей|ий|ый|ой|ем|им|ым|ом|его|ого|еых|ую|юю|ая|яя|ою|ею)$/';
    const PARTICIPLE = '/((ивш|ывш|ующ)|((?<=[ая])(ем|нн|вш|ющ|щ)))$/';
    const VERB = '/((ила|ыла|ена|ейте|уйте|ите|или|ыли|ей|уй|ил|ыл|им|ым|ены|ить|ыть|ишь|ую|ю)|((?<=[ая])(ла|на|ете|йте|ли|й|л|ем|н|ло|но|ет|ют|ны|ть|ешь|нно)))$/';
    const NOUN = '/(а|ев|ов|ие|ье|е|иями|ями|ами|еи|ии|и|ией|ей|ой|ий|й|и|ы|ь|ию|ью|ю|ия|ья|я)$/';
    const RVRE = '/^(.*?[аеиоуыэюя])(.*)$/';
    const DERIVATIONAL = '/.*[^аеиоуыэюя]+[аеиоуыэюя].*ость?$/';
    const DER = '/ость?$/';
    const SUPERLATIVE = "/(ейше|ейш)$/";
    const I = "/и$/";
    const P = "/ь$/";
    const NN = "/нн$/";

    protected static function replace($pattern, $replacement, &$string)
    {
        $temp = $string;
        $string = preg_replace($pattern.'u', $replacement, $string);
        return $temp !== $string;
    }

    public static function stem($word)
    {
        $word = mb_strtolower($word);
        $word = str_replace('ё', 'е', $word);

        if (preg_match(self::RVRE.'u', $word, $matches)) {
            $pre = $matches[1];
            $rv = $matches[2];
            # step 1
            if (!self::replace(self::PERFECTIVEGROUND, '', $rv)) {
                self::replace(self::REFLEXIVE, '', $rv);
                if (self::replace(self::ADJECTIVE, '', $rv)) {
                    self::replace(self::PARTICIPLE, '', $rv);
                } else {
                    if (!self::replace(self::VERB, '', $rv)) {
                        self::replace(self::NOUN, '', $rv);
                    }
                }
            }

            # step 2
            self::replace(self::I, '', $rv);

            # step 3
            if (preg_match(self::DERIVATIONAL.'u', $rv)) {
                self::replace(self::DER, '', $rv);
            }

            # step 4
            if (!self::replace(self::P, '', $rv)) {
                self::replace(self::SUPERLATIVE, '', $rv);
                self::replace(self::NN, 'н', $rv);
            }
            $word = $pre.$rv;
        }
        return $word;
   }

}