<?php

namespace GetId3\Module\Audio;

use GetId3\Handler\BaseHandler;
use GetId3\Lib\Helper;

/////////////////////////////////////////////////////////////////
/// GetId3() by James Heinrich <info@getid3.org>               //
//  available at http://getid3.sourceforge.net                 //
//            or http://www.getid3.org                         //
/////////////////////////////////////////////////////////////////
// See readme.txt for more details                             //
/////////////////////////////////////////////////////////////////
//                                                             //
// module.audio.avr.php                                        //
// module for analyzing AVR Audio files                        //
// dependencies: NONE                                          //
//                                                            ///
/////////////////////////////////////////////////////////////////

/**
 * module for analyzing AVR Audio files
 *
 * @author James Heinrich <info@getid3.org>
 * @link http://getid3.sourceforge.net
 * @link http://www.getid3.org
 */
class Avr extends BaseHandler
{
    public function analyze()
    {
        $info = &$this->getid3->info;

        // http://cui.unige.ch/OSG/info/AudioFormats/ap11.html
        // http://www.btinternet.com/~AnthonyJ/Atari/programming/avr_format.html
        // offset    type    length    name        comments
        // ---------------------------------------------------------------------
        // 0    char    4    ID        format ID == "2BIT"
        // 4    char    8    name        sample name (unused space filled with 0)
        // 12    short    1    mono/stereo    0=mono, -1 (0xFFFF)=stereo
        //                     With stereo, samples are alternated,
        //                     the first voice is the left :
        //                     (LRLRLRLRLRLRLRLRLR...)
        // 14    short    1    resolution    8, 12 or 16 (bits)
        // 16    short    1    signed or not    0=unsigned, -1 (0xFFFF)=signed
        // 18    short    1    loop or not    0=no loop, -1 (0xFFFF)=loop on
        // 20    short    1    MIDI note    0xFFnn, where 0 <= nn <= 127
        //                     0xFFFF means "no MIDI note defined"
        // 22    byte    1    Replay speed    Frequence in the Replay software
        //                     0=5.485 Khz, 1=8.084 Khz, 2=10.971 Khz,
        //                     3=16.168 Khz, 4=21.942 Khz, 5=32.336 Khz
        //                     6=43.885 Khz, 7=47.261 Khz
        //                     -1 (0xFF)=no defined Frequence
        // 23    byte    3    sample rate    in Hertz
        // 26    long    1    size in bytes (2 * bytes in stereo)
        // 30    long    1    loop begin    0 for no loop
        // 34    long    1    loop size    equal to 'size' for no loop
        // 38  short   2   Reserved, MIDI keyboard split */
        // 40  short   2   Reserved, sample compression */
        // 42  short   2   Reserved */
        // 44  char   20;  Additional filename space, used if (name[7] != 0)
        // 64    byte    64    user data
        // 128    bytes    ?    sample data    (12 bits samples are coded on 16 bits:
        //                     0000 xxxx xxxx xxxx)
        // ---------------------------------------------------------------------
        // Note that all values are in motorola (big-endian) format, and that long is
        // assumed to be 4 bytes, and short 2 bytes.
        // When reading the samples, you should handle both signed and unsigned data,
        // and be prepared to convert 16->8 bit, or mono->stereo if needed. To convert
        // 8-bit data between signed/unsigned just add 127 to the sample values.
        // Simularly for 16-bit data you should add 32769

        $info['fileformat'] = 'avr';

        fseek($this->getid3->fp, $info['avdataoffset'], SEEK_SET);
        $AVRheader = fread($this->getid3->fp, 128);

        $info['avr']['raw']['magic'] = substr($AVRheader, 0, 4);
        $magic = '2BIT';
        if ($info['avr']['raw']['magic'] != $magic) {
            $info['error'][] = 'Expecting "' . Helper::PrintHexBytes($magic) . '" at offset ' . $info['avdataoffset'] . ', found "' . Helper::PrintHexBytes($info['avr']['raw']['magic']) . '"';
            unset($info['fileformat']);
            unset($info['avr']);
            return false;
        }
        $info['avdataoffset'] += 128;

        $info['avr']['sample_name'] = rtrim(substr($AVRheader, 4, 8));
        $info['avr']['raw']['mono'] = Helper::BigEndian2Int(substr($AVRheader,
                                                                              12,
                                                                              2));
        $info['avr']['bits_per_sample'] = Helper::BigEndian2Int(substr($AVRheader,
                                                                                  14,
                                                                                  2));
        $info['avr']['raw']['signed'] = Helper::BigEndian2Int(substr($AVRheader,
                                                                                16,
                                                                                2));
        $info['avr']['raw']['loop'] = Helper::BigEndian2Int(substr($AVRheader,
                                                                              18,
                                                                              2));
        $info['avr']['raw']['midi'] = Helper::BigEndian2Int(substr($AVRheader,
                                                                              20,
                                                                              2));
        $info['avr']['raw']['replay_freq'] = Helper::BigEndian2Int(substr($AVRheader,
                                                                                     22,
                                                                                     1));
        $info['avr']['sample_rate'] = Helper::BigEndian2Int(substr($AVRheader,
                                                                              23,
                                                                              3));
        $info['avr']['sample_length'] = Helper::BigEndian2Int(substr($AVRheader,
                                                                                26,
                                                                                4));
        $info['avr']['loop_start'] = Helper::BigEndian2Int(substr($AVRheader,
                                                                             30,
                                                                             4));
        $info['avr']['loop_end'] = Helper::BigEndian2Int(substr($AVRheader,
                                                                           34, 4));
        $info['avr']['midi_split'] = Helper::BigEndian2Int(substr($AVRheader,
                                                                             38,
                                                                             2));
        $info['avr']['sample_compression'] = Helper::BigEndian2Int(substr($AVRheader,
                                                                                     40,
                                                                                     2));
        $info['avr']['reserved'] = Helper::BigEndian2Int(substr($AVRheader,
                                                                           42, 2));
        $info['avr']['sample_name_extra'] = rtrim(substr($AVRheader, 44, 20));
        $info['avr']['comment'] = rtrim(substr($AVRheader, 64, 64));

        $info['avr']['flags']['stereo'] = (($info['avr']['raw']['mono'] == 0) ? false : true);
        $info['avr']['flags']['signed'] = (($info['avr']['raw']['signed'] == 0) ? false : true);
        $info['avr']['flags']['loop'] = (($info['avr']['raw']['loop'] == 0) ? false : true);

        $info['avr']['midi_notes'] = array();
        if (($info['avr']['raw']['midi'] & 0xFF00) != 0xFF00) {
            $info['avr']['midi_notes'][] = ($info['avr']['raw']['midi'] & 0xFF00) >> 8;
        }
        if (($info['avr']['raw']['midi'] & 0x00FF) != 0x00FF) {
            $info['avr']['midi_notes'][] = ($info['avr']['raw']['midi'] & 0x00FF);
        }

        if (($info['avdataend'] - $info['avdataoffset']) != ($info['avr']['sample_length'] * (($info['avr']['bits_per_sample'] == 8) ? 1 : 2))) {
            $info['warning'][] = 'Probable truncated file: expecting ' . ($info['avr']['sample_length'] * (($info['avr']['bits_per_sample'] == 8) ? 1 : 2)) . ' bytes of audio data, found ' . ($info['avdataend'] - $info['avdataoffset']);
        }

        $info['audio']['dataformat'] = 'avr';
        $info['audio']['lossless'] = true;
        $info['audio']['bitrate_mode'] = 'cbr';
        $info['audio']['bits_per_sample'] = $info['avr']['bits_per_sample'];
        $info['audio']['sample_rate'] = $info['avr']['sample_rate'];
        $info['audio']['channels'] = ($info['avr']['flags']['stereo'] ? 2 : 1);
        $info['playtime_seconds'] = ($info['avr']['sample_length'] / $info['audio']['channels']) / $info['avr']['sample_rate'];
        $info['audio']['bitrate'] = ($info['avr']['sample_length'] * (($info['avr']['bits_per_sample'] == 8) ? 8 : 16)) / $info['playtime_seconds'];


        return true;
    }
}
