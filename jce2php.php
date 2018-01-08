<?php
/**
 *
 * 说明：
 *    1. } 要单独放在一行，如}后面有;号，要跟}放在一行
 *    2. 每行只能字义一个字段
 *    3. struct不能嵌套定义，如用到别的struct, 把对应的struct 拿出来定义即可
 *
 **/

$fromFile = $argv[1];
$servantName = $argv[2];
$outputDir = $argv[3];
$inputDir = $argv[4];

if(empty($servantName)) {
    echo "Please input serantName!!";
    exit;
}

class Utils {
    public static $preEnums;
    public static $preStructs;

    public static function getPackMethods($type) {
        $packMethods = [
            'bool' => 'putBool',
            'byte' => 'putUInt8',
            'char' => 'putChar',
            'unsigned byte' => 'putUInt8',
            'unsigned char' => 'putUInt8',
            'short' => 'putShort',
            'unsigned short' => 'putUInt16',
            'int' => 'putInt32',
            'unsigned int' => 'putUInt32',
            'long' => 'putInt64',
            'float' => 'putFloat',
            'double' => 'putDouble',
            'string' => 'putString',
            'enum' => 'putShort',
            'map' => 'putMap',
            'vector' => 'putVector',
            'Bool' => 'putBool',
            'Byte' => 'putUInt8',
            'Char' => 'putChar',
            'Unsigned byte' => 'putUInt8',
            'Unsigned char' => 'putUInt8',
            'Short' => 'putShort',
            'Unsigned short' => 'putUInt16',
            'Int' => 'putInt32',
            'Unsigned int' => 'putUInt32',
            'Long' => 'putInt64',
            'Float' => 'putFloat',
            'Double' => 'putDouble',
            'String' => 'putString',
            'Enum' => 'putShort',
            'Map' => 'putMap',
            'Vector' => 'putVector'
        ];

        if(isset($packMethods[$type]))
            return $packMethods[$type];
        else return 'putStruct';
    }

    public static function getUnpackMethods($type) {
        $unpackMethods = [
            'bool' => 'getBool',
            'byte' => 'getUInt8',
            'char' => 'getChar',
            'unsigned byte' => 'getUInt8',
            'unsigned char' => 'getUInt8',
            'short' => 'getShort',
            'unsigned short' => 'getUInt16',
            'int' => 'getInt32',
            'unsigned int' => 'getUInt32',
            'long' => 'getInt64',
            'float' => 'getFloat',
            'double' => 'getDouble',
            'string' => 'getString',
            'enum' => 'getShort',
            'map' => 'getMap',
            'vector' => 'getVector',
            'Bool' => 'getBool',
            'Byte' => 'getUInt8',
            'Char' => 'getChar',
            'Unsigned byte' => 'getUInt8',
            'Unsigned char' => 'getUInt8',
            'Short' => 'getShort',
            'Unsigned short' => 'getUInt16',
            'Int' => 'getInt32',
            'Unsigned int' => 'getUInt32',
            'Long' => 'getInt64',
            'Float' => 'getFloat',
            'Double' => 'getDouble',
            'String' => 'getString',
            'Enum' => 'getShort',
            'Map' => 'getMap',
            'Vector' => 'getVector'
        ];


        if(isset($unpackMethods[strtolower($type)]))
            return $unpackMethods[strtolower($type)];
        else return 'getStruct';
    }

    /**
     * @param $char
     * @return int
     * 判断是不是tag
     */
    public static function isTag($word) {
        if(!empty(strval($word)) && intval($word) == 0) {
            return false;
        }
        else return true;
    }

    /**
     * @param $word
     * @return bool
     * 判断收集到的word是不是
     */
    public static function isRequireType($word) {
        return in_array(strtolower($word),['require','optional']);
    }

    public static function isBasicType($word) {
        $basicTypes = [
            'bool','byte','char','unsigned byte', 'unsigned char', 'short','unsigned short',
            'int' ,'unsigned int','long','float','double','string', 'void'
        ];
        return in_array(strtolower($word),$basicTypes);
    }

    public static function isEnum($word,$preEnums) {
        return in_array($word,$preEnums);
    }

    public static function isMap($word) {
        return strtolower($word) == 'map';
    }

    public static function isStruct($word,$preStructs) {
        return in_array($word,$preStructs);
    }

    public static function isVector($word) {
        return strtolower($word) == 'vector';
    }

    public static function isSpace($char) {
        if($char == ' ' || $char == "\t")
            return true;
        else return false;
    }

    public static function paramTypeMap($paramType) {
        if(Utils::isBasicType($paramType) || Utils::isMap($paramType) || Utils::isVector($paramType)) {
            return "";
        }
        else {
            return $paramType;
        }
    }

    public static function inIdentifier($char) {
        return ($char >= 'a' & $char <= 'z') |
        ($char >= 'A' & $char <= 'Z')|
        ($char >= '0' & $char <= '9') |
        ($char == '_');
    }


    public static function abnormalExit($level,$msg) {
        echo "[$level]$msg"."\n";
        exit;
    }

    public static function pregMatchByName($name='enum',$line) {
        // 处理第一行,正则匹配出classname
        $Tokens = preg_split("/$name/", $line);

        $mathName = $Tokens[1];
        $mathName = trim($mathName," \r\0\x0B\t\n{");

        preg_match('/[a-zA-Z][0-9a-zA-Z]/',$mathName,$matches);
        if(empty($matches)) {
            Utils::abnormalExit('error',$name.'名称有误');
        }
        return $mathName;

    }
}

class StructParser {

    public $namespaceName;
    public $uniqueName;
    public $moduleName;
    public $structName;
    public $state;

    // 这个结构体,可能会引用的部分,包括其他的结构体、枚举类型、常量
    public $preStructs;
    public $preEnums;
    public $preNamespaceEnums;
    public $preNamespaceStructs;


    public $returnSymbol = "\n";
    public $doubleReturn = "\n\n";
    public $tabSymbol = "\t";
    public $doubleTab = "\t\t";
    public $tripleTab = "\t\t\t";
    public $quardupleTab = "\t\t\t\t";


    public $extraContructs = '';
    public $extraExtType = '';
    public $extraExtInit = '';

    public $consts='';
    public $variables = '';
    public $fields = '';


    public function __construct($fp,$line,$uniqueName,$moduleName,
                                $structName,$preStructs,$preEnums,$namespaceName,
                                $preNamespaceEnums, $preNamespaceStructs)
    {
        $this->fp = $fp;
        $this->uniqueName = $uniqueName;
        $this->namespaceName = $namespaceName;
        $this->moduleName = $moduleName;
        $this->preStructs = $preStructs;
        $this->preEnums = $preEnums;
        $this->structName = $structName;

        $this->consts = '';
        $this->variables = '';
        $this->fields = '';

        $this->preNamespaceEnums = $preNamespaceEnums;
        $this->preNamespaceStructs = $preNamespaceStructs;

    }

    /**
     * @param $char
     * @return int
     * 判断是不是tag
     */
    public function isTag($word) {
        if(!empty(strval($word)) && intval($word) == 0) {
            return false;
        }
        else return true;
    }

    /**
     * @param $word
     * @return bool
     * 判断收集到的word是不是
     */
    public function isRequireType($word) {
        return in_array(strtolower($word),['require','optional']);
    }

    public function isBasicType($word) {
        $basicTypes = [
            'bool','byte','char','unsigned byte', 'unsigned char', 'short','unsigned short',
            'int' ,'unsigned int','long','float','double','string', 'void'
        ];
        return in_array(strtolower($word),$basicTypes);
    }

    public function isEnum($word) {
        return in_array($word,$this->preEnums);
    }

    public function isMap($word) {
        return strtolower($word) == 'map';
    }

    public function isStruct($word) {
        return in_array($word,$this->preStructs);
    }

    public function isVector($word) {
        return strtolower($word) == 'vector';
    }

    public static function isSpace($char) {
        if($char == ' ' || $char == "\t")
            return true;
        else return false;
    }

    public function parse() {

        //echo "[info]Struct With Name:".$this->structName." parse start \n\n";

        while ($this->state != 'end') {
            $this->structBodyParseLine();
        }

        // 先把积累下来的三个部分处理掉
        $structClassStr = $this->getStructClassHeader("\\classes").
            "class ".$this->structName." extends \Taf\TJCE_Struct {".$this->returnSymbol;

        $structClassStr  .= $this->consts.$this->doubleReturn;
        $structClassStr .= $this->variables.$this->doubleReturn;
        $fieldsPrefix = $this->tabSymbol."protected static \$fields = array(".$this->returnSymbol;
        $fieldsSuffix = $this->tabSymbol.");".$this->doubleReturn;

        $structClassStr .= $fieldsPrefix;
        $structClassStr .= $this->fields;
        $structClassStr .= $fieldsSuffix;

        // 处理最后一行

        $construct = $this->tabSymbol."public function __construct() {".$this->returnSymbol.
            $this->extraExtType.
            $this->doubleTab."parent::__construct('".$this->uniqueName."_".$this->structName."', self::\$fields);".$this->returnSymbol
            .$this->extraContructs
            .$this->extraExtInit
            .$this->tabSymbol."}".$this->returnSymbol;

        $structClassStr .= $construct."}".$this->returnSymbol;

        //echo "[info]Struct With Name:".$this->structName." parse finish line:\n\n";
        return $structClassStr;
    }


    public function abnormalExit($level,$msg) {
        echo "[$level]$msg,[出错结构体struct]:".$this->structName."\n";
        exit;
    }

    /**
     * @param $startChar
     * @param $lineString
     * @return string
     * 专门处理注释
     */
    public function copyAnnotation($startChar,$lineString) {
        $lineString .= $startChar;
        // 再读入一个字符
        $nextChar = fgetc($this->fp);
        //echo "[debug][".__METHOD__."] nextChar:".$nextChar." startChar:".$startChar."\n";
        // 第一种
        if($nextChar == '/') {
            $lineString .= $nextChar;
            while (1) {
                $tmpChar = fgetc($this->fp);
                if($tmpChar == "\n") {

                    $this->state = 'lineEnd';
                    break;
                }
                $lineString .= $tmpChar;
            }
            return $lineString;
        }
        else if($nextChar == '*') {
            $lineString .= $nextChar;
            while (1) {
                $tmpChar =fgetc($this->fp);
                $lineString .= $tmpChar;

                if($tmpChar === false) {
                    $this->abnormalExit('error','注释换行错误,请检查');
                }
                else if($tmpChar === "\n") {

                }
                else if(($tmpChar) === '*') {
                    $nextnextChar = fgetc($this->fp);
                    if($nextnextChar == '/') {
                        $lineString .= $nextnextChar;
                        return $lineString;
                    }
                    else{
                        $pos = ftell($this->fp);
                        fseek($this->fp,$pos - 1);
                    }
                }

            }
        }
        // 注释不正常
        else {
            $this->abnormalExit('error','注释换行错误,请检查');
        }
    }

    public function inIdentifier($char) {
        return ($char >= 'a' & $char <= 'z') |
        ($char >= 'A' & $char <= 'Z')|
        ($char >= '0' & $char <= '9') |
        ($char == '_');
    }

    /**
     * @param $fp
     * @param $line
     * 这里必须要引入状态机了
     */
    public function structBodyParseLine() {

        $validLine = false;

        $this->state = 'init';

        $lineString = '';
        $word = '';

        $mapVectorState = false;
        while (1) {
            $char =fgetc($this->fp);

            if($this->state == 'init') {
                // 有可能是换行
                if($char == '{' || $this->isSpace($char)) {
                    continue;
                }
                else if($char == "\n") {
                    break;
                }
                else if($char == ";") {
                    break;
                }
                // 遇到了注释会用贪婪算法全部处理完,同时填充到struct的类里面去
                else if($char == '/') {
                    $lineString = $this->copyAnnotation($char,$lineString);
                    break;
                }
                else if($this->inIdentifier($char)) {
                    $this->state = 'identifier';
                    $word .= $char;
                }
                // 终止条件之1,宣告struct结束
                else if($char == '}') {
                    // 需要贪心的读到"\n"为止
                    while(($lastChar=fgetc($this->fp)) != "\n") {
                        continue;
                    }
                    $this->state = 'end';
                    break;
                }
                else if($char == '=') {
                    //遇到等号,可以贪婪的向后,直到遇到;或者换行符
                    if(!empty($word))
                        $valueName = $word;
                    $moreChar = fgetc($this->fp);

                    $defaultValue = '';

                    while($moreChar != '\n' && $moreChar != ';' && $moreChar != '}') {
                        $defaultValue .= $moreChar;

                        $moreChar = fgetc($this->fp);
                    }
                    //if(empty($defaultValue)) {
                    //    $this->abnormalExit('error','结构体内默认值格式错误,请更正jce');
                    //}

                    if($moreChar == '}'){
                        // 需要贪心的读到"\n"为止
                        while(($lastChar=fgetc($this->fp)) != "\n") {
                            continue;
                        }
                        $this->state = 'end';
                    }
                    else $this->state = 'init';
                }
                else {
                    //echo "char:".$char." word:".$word."\n"."tag:".$tag;

                    //$this->abnormalExit('error','结构体内格式错误,请更正jce');
                    continue;
                }
            }
            else if($this->state == 'identifier') {
                $validLine = true;
                // 如果遇到了space,需要检查是不是在map或vector的类型中,如果当前积累的word并不合法
                // 并且又不是处在vector或map的前置状态下的话,那么就是出错了
                //echo "[debug][state={$this->state}]word:".$word."\n";
                if($this->isSpace($char)) {
                    if($this->isTag($word)) {
                        $tag = $word;
                        $this->state = 'init';
                        $word = '';
                    }
                    else if($this->isRequireType($word)) {
                        $requireType = $word;
                        $this->state = 'init';
                        $word = '';
                    }
                    else if($this->isBasicType($word)) {
                        $type = $word;
                        $this->state = 'init';
                        $word = '';
                    }
                    else if($this->isStruct($word)) {
                        $type = $word;
                        $this->state = 'init';
                        $word = '';
                    }
                    else if($this->isEnum($word)) {
                        $type = 'unsigned byte';
                        $this->state = 'init';
                        $word = '';
                    }
                    // 增加对namespace的支持
                    else if(in_array($word,$this->preNamespaceStructs)) {
                        $type = explode("::",$word);
                        $type = $type[1];
                        $this->state = 'init';
                        $word = '';
                    }
                    // 增加对namespace的支持
                    else if(in_array($word,$this->preNamespaceEnums)) {
                        $type = 'unsigned byte';
                        $this->state = 'init';
                        $word = '';
                    }
                    else {
                        // 读到了vector和map中间的空格,还没读完
                        if($mapVectorState) {
                            continue;
                        }
                        // 否则剩余的部分应该就是值和默认值
                        else {
                            if(!empty($word))
                                $valueName = $word;
                            $this->state = 'init';
                            $word = '';
                        }
                    }
                }
                // 标志着map和vector的开始,不等到'>'的结束不罢休
                // 这时候需要使用栈来push,然后一个个对应的pop,从而达到type的遍历
                else if($char == '<') {
                    // 贪婪的向后,直到找出所有的'>'
                    $type = $word;
                    // 还会有一个wholeType,表示完整的部分
                    $mapVectorStack = [];
                    $wholeType = $type;
                    $wholeType .= '<';
                    array_push($mapVectorStack,'<');
                    while(!empty($mapVectorStack)) {
                        $moreChar = fgetc($this->fp);
                        $wholeType .= $moreChar;
                        if($moreChar == '<') {
                            array_push($mapVectorStack,'<');
                        }
                        else if($moreChar == '>') {
                            array_pop($mapVectorStack);
                        }
                    }

                    $this->state = 'init';
                    $word = '';
                }
                else if($char == '=') {
                    //遇到等号,可以贪婪的向后,直到遇到;或者换行符
                    if(!empty($word))
                        $valueName = $word;
                    $moreChar = fgetc($this->fp);

                    $defaultValue = '';

                    while($moreChar != '\n' && $moreChar != ';' && $moreChar != '}') {
                        $defaultValue .= $moreChar;

                        $moreChar = fgetc($this->fp);
                    }
                    //if(empty($defaultValue)) {
                    //    $this->abnormalExit('error','结构体内默认值格式错误,请更正jce');
                    //}

                    if($moreChar == '}'){
                        // 需要贪心的读到"\n"为止
                        while(($lastChar=fgetc($this->fp)) != "\n") {
                            continue;
                        }
                        $this->state = 'end';
                    }
                    else $this->state = 'init';
                }
                else if ($char == ';') {
                    if(!empty($word)) {
                        $valueName = $word;
                    }
                    continue;
                }
                // 终止条件之2,同样宣告struct结束
                else if($char == '}') {
                    // 需要贪心的读到"\n"为止
                    while(($lastChar=fgetc($this->fp)) != "\n") {
                        continue;
                    }
                    $this->state = 'end';
                }
                else if($char == '/') {
                    $lineString = $this->copyAnnotation($char,$lineString);
                }
                else if($char == "\n"){
                    break;
                }
                else $word .= $char;
            }
            else if($this->state == 'lineEnd') {
                if($char == '}'){
                    // 需要贪心的读到"\n"为止
                    while(($lastChar=fgetc($this->fp)) != "\n") {
                        continue;
                    }
                    $this->state = 'end';
                }
                break;
            }
            else if($this->state == 'end') {
                break;
            }
        }

        if(!$validLine) {
            //echo "Not a useful line.\n";
            return;
        }

        //echo "RAW tag:".$tag." requireType:".$requireType." type:".$type.
        //    " valueName:".$valueName. " wholeType:".$wholeType.
        //    " defaultValue:".$defaultValue." lineString:".$lineString."\n\n";

        // 完成了这一行的词法解析,需要输出如下的字段

        if(!isset($tag) || empty($requireType) || empty($type) || empty($valueName)) {
            $this->abnormalExit('error','结构体内格式错误,请更正jce');
        }
        else if($type == 'map' && empty($wholeType)) {
            $this->abnormalExit('error','结构体内map格式错误,请更正jce');
        }
        else if($type == 'vector' && empty($wholeType)) {
            $this->abnormalExit('error','结构体内vector格式错误,请更正jce');
        }
        else {
            // echo "Valid tag:".$tag." requireType:".$requireType." type:".$type." valueName:".$valueName.
            //    " wholeType:".$wholeType." defaultValue:".$defaultValue."\n";

            $this->writeStructLine($tag,$requireType,$type,$valueName,$wholeType,$defaultValue);
        }
    }

    public $typeMap = array(
        'bool' => '\Taf\TJCE::BOOL',
        'byte' => '\Taf\TJCE::CHAR',
        'char' => '\Taf\TJCE::CHAR',
        'unsigned byte' => '\Taf\TJCE::UINT8',
        'unsigned char' => '\Taf\TJCE::UINT8',
        'short' => '\Taf\TJCE::SHORT',
        'unsigned short' => '\Taf\TJCE::UINT16',
        'int' => '\Taf\TJCE::INT32',
        'unsigned int' => '\Taf\TJCE::UINT32',
        'long' => '\Taf\TJCE::INT64',
        'float' => '\Taf\TJCE::FLOAT',
        'double' => '\Taf\TJCE::DOUBLE',
        'string' => '\Taf\TJCE::STRING',
        'vector' => '\Taf\TJCE::VECTOR',
        'map' => '\Taf\TJCE::MAP',
        'enum' => '\Taf\TJCE::SHORT',// 应该不会出现
        'struct' => '\Taf\TJCE::STRUCT',// 应该不会出现
        'Bool' => '\Taf\TJCE::BOOL',
        'Byte' => '\Taf\TJCE::CHAR',
        'Char' => '\Taf\TJCE::CHAR',
        'Unsigned byte' => '\Taf\TJCE::UINT8',
        'Unsigned char' => '\Taf\TJCE::UINT8',
        'Short' => '\Taf\TJCE::SHORT',
        'Unsigned short' => '\Taf\TJCE::UINT16',
        'Int' => '\Taf\TJCE::INT32',
        'Unsigned int' => '\Taf\TJCE::UINT32',
        'Long' => '\Taf\TJCE::INT64',
        'Float' => '\Taf\TJCE::FLOAT',
        'Double' => '\Taf\TJCE::DOUBLE',
        'String' => '\Taf\TJCE::STRING',
        'Vector' => '\Taf\TJCE::VECTOR',
        'Map' => '\Taf\TJCE::MAP',
        'Enum' => '\Taf\TJCE::SHORT',// 应该不会出现
        'Struct' => '\Taf\TJCE::STRUCT',// 应该不会出现
    );

    public $wholeTypeMap = array(
        'bool' => '\Taf\TJCE::BOOL',
        'byte' => '\Taf\TJCE::CHAR',
        'char' => '\Taf\TJCE::CHAR',
        'unsigned byte' => '\Taf\TJCE::UINT8',
        'unsigned char' => '\Taf\TJCE::UINT8',
        'short' => '\Taf\TJCE::SHORT',
        'unsigned short' => '\Taf\TJCE::UINT16',
        'int' => '\Taf\TJCE::INT32',
        'unsigned int' => '\Taf\TJCE::UINT32',
        'long' => '\Taf\TJCE::INT64',
        'float' => '\Taf\TJCE::FLOAT',
        'double' => '\Taf\TJCE::DOUBLE',
        'string' => '\Taf\TJCE::STRING',
        'vector' => 'new \Taf\TJCE_VECTOR',
        'map' => 'new \Taf\TJCE_MAP',
        'Bool' => '\Taf\TJCE::BOOL',
        'Byte' => '\Taf\TJCE::CHAR',
        'Char' => '\Taf\TJCE::CHAR',
        'Unsigned byte' => '\Taf\TJCE::UINT8',
        'Unsigned char' => '\Taf\TJCE::UINT8',
        'Short' => '\Taf\TJCE::SHORT',
        'Unsigned short' => '\Taf\TJCE::UINT16',
        'Int' => '\Taf\TJCE::INT32',
        'Unsigned int' => '\Taf\TJCE::UINT32',
        'Long' => '\Taf\TJCE::INT64',
        'Float' => '\Taf\TJCE::FLOAT',
        'Double' => '\Taf\TJCE::DOUBLE',
        'String' => '\Taf\TJCE::STRING',
        'Vector' => 'new \Taf\TJCE_VECTOR',
        'Map' => 'new \Taf\TJCE_MAP',
    );

    public function getRealType($type) {
        if(isset($this->typeMap[strtolower($type)])) return $this->typeMap[strtolower($type)];
        else return '\Taf\TJCE::STRUCT';
    }

    /**
     * @param $wholeType
     * 通过完整的类型获取vector的扩展类型
     * vector<CateObj> => new \Taf\TJCE_VECTOR(new CateObj())
     * vector<string> => new \Taf\TJCE_VECTOR(\Taf\TJCE::STRING)
     * vector<map<string,CateObj>> => new \Taf\TJCE_VECTOR(new \Taf\TJCE_MAP(\Taf\TJCE_MAP,new CateObj()))
     */
    public function getExtType($wholeType) {
        $state = 'init';
        $word = '';
        $extType = '';

        for($i = 0; $i < strlen($wholeType);$i++) {
            $char = $wholeType[$i];
            if($state == 'init') {
                // 如果遇到了空格
                if(self::isSpace($char)) {
                    continue;
                }
                // 回车是停止符号
                else if($this->inIdentifier($char)) {
                    $state = 'indentifier';
                    $word .= $char;
                }
                else if($char == '\n') {
                    break;
                }
                else if($char == '>') {
                    $extType .= ")";
                    continue;
                }
            }
            else if($state == 'indentifier') {
                if($char == '<') {
                    // 替换word,替换< 恢复初始状态
                    $tmp = $this->VecMapReplace($word);
                    $extType .= $tmp;
                    $extType .= "(";
                    $word = '';
                    $state = 'init';
                }
                else if($char == '>') {
                    // 替换word,替换> 恢复初始状态
                    // 替换word,替换< 恢复初始状态
                    $tmp = $this->VecMapReplace($word);
                    $extType .= $tmp;
                    $extType .= ")";
                    $word = '';
                    $state = 'init';
                }
                else if($char == ',') {
                    // 替换word,替换, 恢复初始状态
                    // 替换word,替换< 恢复初始状态
                    $tmp = $this->VecMapReplace($word);
                    $extType .= $tmp;
                    $extType .= ",";
                    $word = '';
                    $state = 'init';
                }
                else {
                    $word .= $char;
                    continue;
                }
            }
        }
        return $extType;
    }


    public function VecMapReplace($word) {

        $word = trim($word);
        // 遍历所有的类型
        foreach ($this->wholeTypeMap as $key => $value) {
            if($this->isStruct($word)) {
                $word = "new " . $word . "()";
                break;
            }
            else $word = str_replace($key,$value,$word);
        }

        /*if($this->isStruct($word)) {
            $word = "new " . $word . "()";
        }*/

        return $word;
    }



    /**
     * @param $tag
     * @param $requireType
     * @param $type
     * @param $name
     * @param $wholeType
     * @param $defaultValue
     */
    public function writeStructLine($tag,$requireType,$type,$valueName,$wholeType,$defaultValue) {
        if($requireType === 'require')
            $requireFlag = 'true';
        else $requireFlag = 'false';

        $this->consts .= $this->tabSymbol."const ".strtoupper($valueName)." = ".$tag.";".$this->returnSymbol;
        if(!empty($defaultValue)) {
            $this->variables .= $this->tabSymbol."public $".$valueName."=".$defaultValue.";"." ".$this->returnSymbol;
        }
        else $this->variables .= $this->tabSymbol."public $".$valueName.";"." ".$this->returnSymbol;

        // 基本类型,直接替换
        if($this->isBasicType($type)) {
            $this->fields .= $this->doubleTab."self::".strtoupper($valueName)." => array(".$this->returnSymbol.
                $this->tripleTab."'name'=>'".$valueName."',".$this->returnSymbol.
                $this->tripleTab."'required'=>".$requireFlag.",".$this->returnSymbol.
                $this->tripleTab."'type'=>".$this->getRealType($type).",".$this->returnSymbol.
                $this->tripleTab."),".$this->returnSymbol;

        }
        else if($this->isStruct($type)) {
            $this->fields .= $this->doubleTab."self::".strtoupper($valueName)." => array(".$this->returnSymbol.
                $this->tripleTab."'name'=>'".$valueName."',".$this->returnSymbol.
                $this->tripleTab."'required'=>".$requireFlag.",".$this->returnSymbol.
                $this->tripleTab."'type'=>".$this->getRealType($type).",".$this->returnSymbol.
                $this->tripleTab."),".$this->returnSymbol;
            $this->extraContructs .= $this->doubleTab."\$this->$valueName = new $type();".$this->returnSymbol;

        }
        else if($this->isVector($type) || $this->isMap($type)) {
            $this->fields .= $this->doubleTab."self::".strtoupper($valueName)." => array(".$this->returnSymbol.
                $this->tripleTab."'name'=>'".$valueName."',".$this->returnSymbol.
                $this->tripleTab."'required'=>".$requireFlag.",".$this->returnSymbol.
                $this->tripleTab."'type'=>".$this->getRealType($type).",".$this->returnSymbol.
                $this->tripleTab."),".$this->returnSymbol;

            $extType = $this->getExtType($wholeType);
            $this->extraExtInit .= $this->doubleTab."\$this->".$valueName."=".$extType.";".$this->returnSymbol;

        }
        else {
            $this->abnormalExit('error','结构体struct内类型有误,请更正jce');
        }
    }


    public function getStructClassHeader($prefix="getFileHea") {
        return "<?php\n\nnamespace protocol\\".$this->namespaceName.$prefix.";".
        $this->doubleReturn;
    }
}

class InterfaceParser {

    public $namespaceName;
    public $moduleName;
    public $interfaceName;
    public $asInterfaceName;
    public $nyInterfaceName;

    public $state;

    // 这个结构体,可能会引用的部分,包括其他的结构体、枚举类型、常量
    public $useStructs=[];
    public $extraUse;
    public $preStructs;
    public $preEnums;

    public $preNamespaceEnums=[];
    public $preNamespaceStructs=[];

    public $firstLine;


    public $returnSymbol = "\n";
    public $doubleReturn = "\n\n";
    public $tabSymbol = "\t";
    public $doubleTab = "\t\t";
    public $tripleTab = "\t\t\t";
    public $quardupleTab = "\t\t\t\t";


    public $extraContructs = '';
    public $extraExtType = '';
    public $extraExtInit = '';

    public $consts='';
    public $variables = '';
    public $fields = '';

    public $use = 'use \\weblib\\taf\\TafAssistantV2;\n\n';

    public $useAs = 'use \\weblib\\taf\\TafAssistantAsV2;\n\n';

    public $useNy = 'use \\weblib\\taf\\TafAssistantNyV2;\n\n';

    public $funcSet = '';
    public $funcSetAs = '';

    public $servantName;


    public function __construct($fp, $line, $namespaceName, $moduleName,
                                $interfaceName, $asInterfaceName, $nyInterfaceName, $preStructs,
                                $preEnums, $servantName, $preNamespaceEnums, $preNamespaceStructs)
    {
        $this->fp = $fp;
        $this->firstLine = $line;
        $this->namespaceName = $namespaceName;
        $this->moduleName = $moduleName;
        $this->preStructs = $preStructs;
        $this->preEnums = $preEnums;
        $this->interfaceName = $interfaceName;
        $this->asInterfaceName = $asInterfaceName;
        $this->nyInterfaceName = $nyInterfaceName;
        $this->servantName = $servantName;

        $this->extraUse = '';
        $this->useStructs = [];

        $this->preNamespaceEnums = $preNamespaceEnums;
        $this->preNamespaceStructs = $preNamespaceStructs;

    }


    public function isBasicType($word) {
        $basicTypes = [
            'bool','byte','char','unsigned byte', 'unsigned char', 'short','unsigned short',
            'int' ,'unsigned int','long','float','double','string', 'void'
        ];
        return in_array(strtolower($word),$basicTypes);
    }

    public function isEnum($word) {
        return in_array($word,$this->preEnums);
    }

    public function isMap($word) {
        return strtolower($word) == 'map';
    }

    public function isStruct($word) {
        return in_array($word,$this->preStructs);
    }

    public function isVector($word) {
        return strtolower($word) == 'vector';
    }

    public static function isSpace($char) {
        if($char == ' ' || $char == "\t")
            return true;
        else return false;
    }

    public function getFileHeader($prefix="") {
        return "<?php\n\nnamespace protocol\\".$this->namespaceName.$prefix.";".
        $this->doubleReturn;
    }

    public function getInterfaceBasic() {

        return $this->tabSymbol."private \$_tafAssistant;".$this->returnSymbol.
        $this->tabSymbol."private \$_servantName = \"$this->servantName\";".$this->doubleReturn.
        $this->tabSymbol."private \$_socketMode=2;".$this->returnSymbol.
        $this->tabSymbol."private \$_iVersion=3;".$this->returnSymbol.
        $this->tabSymbol."private \$_ip;".$this->returnSymbol.
        $this->tabSymbol."private \$_port;".$this->doubleReturn.
        $this->tabSymbol."public function __construct(\$ip=\"\",\$port=\"\",\$callerName=\"\",\$iVersion=3) {".$this->returnSymbol.
        $this->doubleTab."\$this->_tafAssistant = new TafAssistantV2(\$callerName);".$this->returnSymbol.
        $this->doubleTab."\$this->_ip = \$ip;".$this->returnSymbol.
        $this->doubleTab."\$this->_port = \$port;".$this->returnSymbol.
        $this->doubleTab."\$this->_iVersion = \$iVersion;".$this->returnSymbol.
        $this->tabSymbol."}".$this->doubleReturn.

        $this->tabSymbol."public function set(\$key,\$value) {".$this->returnSymbol.
        $this->doubleTab."if(property_exists(\$this,\$key))".$this->returnSymbol.
        $this->tripleTab."\$this->\$key = \$value;".$this->returnSymbol.
        $this->tabSymbol."}".$this->doubleReturn;
    }

    public function getInterfaceBasicAs() {
        return  $this->tabSymbol."private \$_tafAssistant;".$this->returnSymbol.
        $this->tabSymbol."private \$_servantName = \"$this->servantName\";".$this->doubleReturn.
        $this->tabSymbol."private \$_socketMode=2;".$this->returnSymbol.
        $this->tabSymbol."private \$_iVersion=3;".$this->returnSymbol.
        $this->tabSymbol."private \$_ip;".$this->returnSymbol.
        $this->tabSymbol."private \$_port;".$this->doubleReturn.
        $this->tabSymbol."public function __construct(\$ip=\"\",\$port=\"\",\$callerName=\"\",\$iVersion=3) {".$this->returnSymbol.
        $this->doubleTab."\$this->_tafAssistant = new TafAssistantAsV2(\$callerName);".$this->returnSymbol.
        $this->doubleTab."\$this->_ip = \$ip;".$this->returnSymbol.
        $this->doubleTab."\$this->_port = \$port;".$this->returnSymbol.
        $this->doubleTab."\$this->_iVersion = \$iVersion;".$this->returnSymbol.
        $this->tabSymbol."}".$this->doubleReturn.

        $this->tabSymbol."public function set(\$key,\$value) {".$this->returnSymbol.
        $this->doubleTab."if(property_exists(\$this,\$key))".$this->returnSymbol.
        $this->tripleTab."\$this->\$key = \$value;".$this->returnSymbol.
        $this->tabSymbol."}".$this->doubleReturn;
    }

    public function getInterfaceBasicNy() {
        return  $this->tabSymbol."private \$_tafAssistant;".$this->returnSymbol.
        $this->tabSymbol."private \$_servantName = \"$this->servantName\";".$this->doubleReturn.
        $this->tabSymbol."private \$_socketMode=2;".$this->returnSymbol.
        $this->tabSymbol."private \$_iVersion=3;".$this->returnSymbol.
        $this->tabSymbol."private \$_ip;".$this->returnSymbol.
        $this->tabSymbol."private \$_port;".$this->doubleReturn.
        $this->tabSymbol."public function __construct(\$ip=\"\",\$port=\"\",\$callerName=\"\",\$iVersion=3) {".$this->returnSymbol.
        $this->doubleTab."\$this->_tafAssistant = new TafAssistantNyV2(\$callerName);".$this->returnSymbol.
        $this->doubleTab."\$this->_ip = \$ip;".$this->returnSymbol.
        $this->doubleTab."\$this->_port = \$port;".$this->returnSymbol.
        $this->doubleTab."\$this->_iVersion = \$iVersion;".$this->returnSymbol.
        $this->tabSymbol."}".$this->doubleReturn.

        $this->tabSymbol."public function set(\$key,\$value) {".$this->returnSymbol.
        $this->doubleTab."if(property_exists(\$this,\$key))".$this->returnSymbol.
        $this->tripleTab."\$this->\$key = \$value;".$this->returnSymbol.
        $this->tabSymbol."}".$this->doubleReturn;
    }

    public function parse() {

        // echo "[info]Interface With Name:".$this->interfaceName." parse start \n\n";

        while ($this->state != 'end') {
            $this->InterfaceFuncParseLine();
        }

        // 处理类的结尾
        $use = 'use \\weblib\\taf\\TafAssistantV2;'.$this->doubleReturn;
        $useAs = 'use \\weblib\\taf\\TafAssistantAsV2;'.$this->doubleReturn;
        $useNy = 'use \\weblib\\taf\\TafAssistantNyV2;'.$this->doubleReturn;

        $interfaceClass = $this->getFileHeader("").$use.$this->extraUse."class ".$this->interfaceName." {".$this->returnSymbol;

        $interfaceClassAs = $this->getFileHeader("").$useAs.$this->extraUse."class ".$this->asInterfaceName." {".$this->returnSymbol;

        $interfaceClassNy = $this->getFileHeader("").$useNy.$this->extraUse."class ".$this->nyInterfaceName." {".$this->returnSymbol;

        $interfaceClass .= $this->getInterfaceBasic();
        $interfaceClassAs .= $this->getInterfaceBasicAs();
        $interfaceClassNy .= $this->getInterfaceBasicNy();

        $interfaceClass .= $this->funcSet;
        $interfaceClassAs .= $this->funcSetAs;
        $interfaceClassNy .= $this->funcSet;

        $interfaceClass .= "}".$this->doubleReturn;
        $interfaceClassAs .= "}".$this->doubleReturn;
        $interfaceClassNy .= "}" . $this->doubleReturn;

        return [
            'syn' => $interfaceClass,
            'asyn' => $interfaceClassAs,
            'ny' => $interfaceClassNy
        ];
    }


    public function abnormalExit($level,$msg) {
        echo "[$level]$msg,[出错接口名称:]:".$this->interfaceName."\n";
        exit;
    }

    public function inIdentifier($char) {
        return ($char >= 'a' & $char <= 'z') |
        ($char >= 'A' & $char <= 'Z')|
        ($char >= '0' & $char <= '9') |
        ($char == '_');
    }

    /**
     * @param $startChar
     * @param $lineString
     * @return string
     * 专门处理注释
     */
    public function copyAnnotation() {
        // 再读入一个字符
        $nextChar = fgetc($this->fp);
        // 第一种
        if($nextChar == '/') {
            while (1) {
                $tmpChar = fgetc($this->fp);
                if($tmpChar == "\n") {

                    $this->state = 'lineEnd';
                    break;
                }
            }
            return;
        }
        else if($nextChar == '*') {
            while (1) {
                $tmpChar =fgetc($this->fp);

                if($tmpChar === false) {
                    $this->abnormalExit('error','注释换行错误,请检查');
                }
                else if($tmpChar === "\n") {

                }
                else if(($tmpChar) === '*') {
                    $nextnextChar = fgetc($this->fp);
                    if($nextnextChar == '/') {
                        return;
                    }
                    else{
                        $pos = ftell($this->fp);
                        fseek($this->fp,$pos - 1);
                    }
                }
            }
        }
        // 注释不正常
        else {
            $this->abnormalExit('error','注释换行错误,请检查');
        }
    }

    /**
     * @param $fp
     * @param $line
     * 这里必须要引入状态机了
     * 这里并不一定要一个line呀,应该找)作为结束符
     */
    public function InterfaceFuncParseLine() {
        $line = '';
        $this->state = 'init';
        while(1) {

            if($this->state == 'init') {
                $char =fgetc($this->fp);

                // 有可能是换行
                if($char == '{' || $this->isSpace($char) || $char == '\n' || $char == '\r'
                    || $char == '\x0B') {
                    continue;
                }
                // 遇到了注释会用贪婪算法全部处理完,同时填充到struct的类里面去
                else if($char == '/') {
                    $this->copyAnnotation();
                    break;
                }
                else if($this->inIdentifier($char)) {
                    $this->state = 'identifier';
                    $line .= $char;
                }
                // 终止条件之1,宣告struct结束
                else if($char == '}') {
                    // 需要贪心的读到"\n"为止
                    while(($lastChar=fgetc($this->fp)) != "\n") {
                        continue;
                    }
                    $this->state = 'end';
                    break;
                }
            }
            else if($this->state == 'identifier') {
                $char =fgetc($this->fp);

                if($char == '/') {
                    $this->copyAnnotation();
                }
                else if ($char == ';') {
                    $line .= $char;
                    break;
                }
                // 终止条件之2,同样宣告interface结束
                else if($char == '}') {
                    // 需要贪心的读到"\n"为止
                    while(($lastChar=fgetc($this->fp)) != "\n") {
                        continue;
                    }
                    $this->state = 'end';
                }
                else if($char == "\n"){
                    continue;
                }
                else if($char == ')') {
                    $line .= $char;
                    // 需要贪心的读到"\n"为止
                    while(($lastChar=fgetc($this->fp)) != "\n") {
                        continue;
                    }
                    $this->state = 'lineEnd';
                }
                else $line .= $char;
            }
            else if($this->state == 'lineEnd') {
                if($char == '}'){
                    // 需要贪心的读到"\n"为止
                    while(($lastChar=fgetc($this->fp)) != "\n") {
                        continue;
                    }
                    $this->state = 'end';
                }
                break;
            }
            else if($this->state == 'end') {
                break;
            }
        }

        if(empty($line)) return;

        //return;
        //$line = fgets($this->fp, 1024);

        $line = trim($line);

        // 如果空行，或者是注释，或者是大括号就直接略过
        if (!trim($line) || trim($line)[0] === '/' || trim($line)[0] === '*' || trim($line) === '{') {
            return;
        }

        $endFlag = strpos($line, "};");
        if ($endFlag !== false) {
            $this->state = 'end';
            return;
        }

        $endFlag = strpos($line, "}");
        if ($endFlag !== false) {
            $this->state = 'end';
            return;
        }

        // 有必要先分成三个部分,返回类型、接口名、参数列表
        $tokens = preg_split('/\s+/', $line,2);

        $returnType = $tokens[0];
        $rest = $tokens[1];
        $tokens1 = preg_split('/\(/',$rest,2);

        $funcName = $tokens1[0];
        $rest = $tokens1[1];

        // echo "RAW "."returnType:".$returnType."  funcName:".$funcName."\n\n";

        $this->state = 'init';
        $word = '';

        $params = [];

        for($i = 0; $i < strlen($rest); $i++) {
            $char = $rest[$i];

            if($this->state == 'init') {
                // 有可能是换行
                if($char == '(' || $this->isSpace($char)) {
                    continue;
                }
                else if($char == "\n") {
                    break;
                }

                else if($this->inIdentifier($char)) {
                    $this->state = 'identifier';
                    $word .= $char;
                }
                // 终止条件之1,宣告interface结束
                else if($char == ')') {
                    break;
                }
                else {
                    // echo "char:".$char." word:".$word."\n";
                    $this->abnormalExit('error','Interface内格式错误,请更正jce');
                }
            }
            else if($this->state == 'identifier') {
                if($char == ',') {
                    $params[] = $word;
                    $this->state = 'init';
                    $word = '';
                    continue;
                }
                // 标志着map和vector的开始,不等到'>'的结束不罢休
                // 这时候需要使用栈来push,然后一个个对应的pop,从而达到type的遍历
                else if($char == '<') {
                    $mapVectorStack = [];
                    $word .= $char;
                    array_push($mapVectorStack,'<');
                    while(!empty($mapVectorStack)) {
                        $moreChar = $rest[$i+1];
                        $word .= $moreChar;
                        if($moreChar == '<') {
                            array_push($mapVectorStack,'<');
                        }
                        else if($moreChar == '>') {
                            array_pop($mapVectorStack);
                        }
                        $i++;
                    }
                    continue;
                }
                else if($char == ")"){
                    $params[] = $word;
                    break;
                }
                else if ($char == ';') {
                    continue;
                }
                // 终止条件之2,同样宣告struct结束
                else if($char == '}') {
                    $this->state = 'end';
                }
                else if($char == "\n"){
                    break;
                }
                else $word .= $char;
            }
            else if($this->state == 'lineEnd') {
                break;
            }
            else if($this->state == 'end') {
                break;
            }
        }

        // echo "RAW  params:".var_export($params,true)."\n\n";

        $this->writeInterfaceLine($returnType,$funcName,$params);

    }

    public $typeMap = array(
        'bool' => '\Taf\TJCE::BOOL',
        'byte' => '\Taf\TJCE::CHAR',
        'char' => '\Taf\TJCE::CHAR',
        'unsigned byte' => '\Taf\TJCE::UINT8',
        'unsigned char' => '\Taf\TJCE::UINT8',
        'short' => '\Taf\TJCE::SHORT',
        'unsigned short' => '\Taf\TJCE::UINT16',
        'int' => '\Taf\TJCE::INT32',
        'unsigned int' => '\Taf\TJCE::UINT32',
        'long' => '\Taf\TJCE::INT64',
        'float' => '\Taf\TJCE::FLOAT',
        'double' => '\Taf\TJCE::DOUBLE',
        'string' => '\Taf\TJCE::STRING',
        'vector' => '\Taf\TJCE::VECTOR',
        'map' => '\Taf\TJCE::MAP',
        'enum' => '\Taf\TJCE::SHORT',// 应该不会出现
        'struct' => '\Taf\TJCE::STRUCT',// 应该不会出现
        'Bool' => '\Taf\TJCE::BOOL',
        'Byte' => '\Taf\TJCE::CHAR',
        'Char' => '\Taf\TJCE::CHAR',
        'Unsigned byte' => '\Taf\TJCE::UINT8',
        'Unsigned char' => '\Taf\TJCE::UINT8',
        'Short' => '\Taf\TJCE::SHORT',
        'Unsigned short' => '\Taf\TJCE::UINT16',
        'Int' => '\Taf\TJCE::INT32',
        'Unsigned int' => '\Taf\TJCE::UINT32',
        'Long' => '\Taf\TJCE::INT64',
        'Float' => '\Taf\TJCE::FLOAT',
        'Double' => '\Taf\TJCE::DOUBLE',
        'String' => '\Taf\TJCE::STRING',
        'Vector' => '\Taf\TJCE::VECTOR',
        'Map' => '\Taf\TJCE::MAP',
        'Enum' => '\Taf\TJCE::SHORT',// 应该不会出现
        'Struct' => '\Taf\TJCE::STRUCT',// 应该不会出现
    );

    public $wholeTypeMap = array(
        'bool' => '\Taf\TJCE::BOOL',
        'byte' => '\Taf\TJCE::CHAR',
        'char' => '\Taf\TJCE::CHAR',
        'unsigned byte' => '\Taf\TJCE::UINT8',
        'unsigned char' => '\Taf\TJCE::UINT8',
        'short' => '\Taf\TJCE::SHORT',
        'unsigned short' => '\Taf\TJCE::UINT16',
        'int' => '\Taf\TJCE::INT32',
        'unsigned int' => '\Taf\TJCE::UINT32',
        'long' => '\Taf\TJCE::INT64',
        'float' => '\Taf\TJCE::FLOAT',
        'double' => '\Taf\TJCE::DOUBLE',
        'string' => '\Taf\TJCE::STRING',
        'vector' => 'new \Taf\TJCE_VECTOR',
        'map' => 'new \Taf\TJCE_MAP',
        'Bool' => '\Taf\TJCE::BOOL',
        'Byte' => '\Taf\TJCE::CHAR',
        'Char' => '\Taf\TJCE::CHAR',
        'Unsigned byte' => '\Taf\TJCE::UINT8',
        'Unsigned char' => '\Taf\TJCE::UINT8',
        'Short' => '\Taf\TJCE::SHORT',
        'Unsigned short' => '\Taf\TJCE::UINT16',
        'Int' => '\Taf\TJCE::INT32',
        'Unsigned int' => '\Taf\TJCE::UINT32',
        'Long' => '\Taf\TJCE::INT64',
        'Float' => '\Taf\TJCE::FLOAT',
        'Double' => '\Taf\TJCE::DOUBLE',
        'String' => '\Taf\TJCE::STRING',
        'Vector' => 'new \Taf\TJCE_VECTOR',
        'Map' => 'new \Taf\TJCE_MAP',
    );

    public function getRealType($type) {
        if(isset($this->typeMap[strtolower($type)])) return $this->typeMap[strtolower($type)];
        else return '\Taf\TJCE::STRUCT';
    }

    /**
     * @param $wholeType
     * 通过完整的类型获取vector的扩展类型
     * vector<CateObj> => new \Taf\TJCE_VECTOR(new CateObj())
     * vector<string> => new \Taf\TJCE_VECTOR(\Taf\TJCE::STRING)
     * vector<map<string,CateObj>> => new \Taf\TJCE_VECTOR(new \Taf\TJCE_MAP(\Taf\TJCE_MAP,new CateObj()))
     */
    public function getExtType($wholeType) {
        $state = 'init';
        $word = '';
        $extType = '';

        for($i = 0; $i < strlen($wholeType);$i++) {
            $char = $wholeType[$i];
            if($state == 'init') {
                // 如果遇到了空格
                if(self::isSpace($char)) {
                    continue;
                }
                // 回车是停止符号
                else if($this->inIdentifier($char)) {
                    $state = 'indentifier';
                    $word .= $char;
                }
                else if($char == '\n') {
                    break;
                }
                else if($char == '>') {
                    $extType .= ")";
                    continue;
                }
            }
            else if($state == 'indentifier') {
                if($char == '<') {
                    // 替换word,替换< 恢复初始状态
                    $tmp = $this->VecMapReplace($word);
                    $extType .= $tmp;
                    $extType .= "(";
                    $word = '';
                    $state = 'init';
                }
                else if($char == '>') {
                    // 替换word,替换> 恢复初始状态
                    // 替换word,替换< 恢复初始状态
                    $tmp = $this->VecMapReplace($word);
                    $extType .= $tmp;
                    $extType .= ")";
                    $word = '';
                    $state = 'init';
                }
                else if($char == ',') {
                    // 替换word,替换, 恢复初始状态
                    // 替换word,替换< 恢复初始状态
                    $tmp = $this->VecMapReplace($word);
                    $extType .= $tmp;
                    $extType .= ",";
                    $word = '';
                    $state = 'init';
                }
                else {
                    $word .= $char;
                    continue;
                }
            }
        }

        return $extType;
    }


    public function VecMapReplace($word) {
        $word = trim($word);
        // 遍历所有的类型
        foreach ($this->wholeTypeMap as $key => $value) {
            if($this->isStruct($word)) {
                if(!in_array($word,$this->useStructs)) {
                    $this->extraUse .= "use protocol\\".$this->namespaceName."\\classes\\".$word.";".$this->returnSymbol;
                    $this->useStructs[] = $word;
                }

                $word = "new " . $word . "()";
                break;
            }
            else if(in_array($word,$this->preNamespaceStructs)) {
                $words = explode("::",$word);
                $word = $words[1];
                if(!in_array($word,$this->useStructs)) {
                    $this->extraUse .= "use protocol\\".$this->namespaceName."\\classes\\".$word.";".$this->returnSymbol;
                    $this->useStructs[] = $word;
                }

                $word = "new " . $word . "()";
                break;
            }
            else $word = str_replace($key,$value,$word);
        }

        return $word;
    }


    public function paramParser($params)
    {

        // 输入和输出的参数全部捋一遍
        $inParams = [];
        $outParams = [];
        foreach ($params as $param) {
            $this->state = 'init';
            $word = '';
            $wholeType = '';
            $paramType = 'in';
            $type = '';
            $mapVectorState = false;

            for ($i = 0; $i < strlen($param) ; $i++) {
                $char = $param[$i];
                if($this->state == 'init') {
                    // 有可能是换行
                    if($this->isSpace($char)) {
                        continue;
                    }
                    else if($char == "\n") {
                        break;
                    }
                    else if($this->inIdentifier($char)) {
                        $this->state = 'identifier';
                        $word .= $char;
                    }
                    else {
                        // echo "char:".$char." word:".$word."\n";

                        $this->abnormalExit('error','Interface内格式错误,请更正jce');
                    }
                }
                else if($this->state == 'identifier') {
                    // 如果遇到了space,需要检查是不是在map或vector的类型中,如果当前积累的word并不合法
                    // 并且又不是处在vector或map的前置状态下的话,那么就是出错了
                    //echo "[debug][state={$this->state}]word:".$word."\n";
                    if($this->isSpace($char)) {
                        if($word == 'out') {
                            $paramType = $word;
                            $this->state = 'init';
                            $word = '';
                        }
                        else if($this->isBasicType($word)) {
                            $type = $word;
                            $this->state = 'init';
                            $word = '';
                        }
                        else if($this->isStruct($word)) {

                            // 同时要把它增加到本Interface的依赖中
                            if(!in_array($word,$this->useStructs)) {
                                $this->extraUse .= "use protocol\\".$this->namespaceName."\\classes\\".$word.";".$this->returnSymbol;
                                $this->useStructs[] = $word;
                            }


                            $type = $word;
                            $this->state = 'init';
                            $word = '';
                        }
                        else if($this->isEnum($word)) {
                            $type = 'short';
                            $this->state = 'init';
                            $word = '';
                        }
                        else if(in_array($word,$this->preNamespaceStructs)) {
                            $word = explode("::",$word);
                            $word = $word[1];
                            // 同时要把它增加到本Interface的依赖中
                            if(!in_array($word,$this->useStructs)) {
                                $this->extraUse .= "use protocol\\".$this->namespaceName."\\classes\\".$word.";".$this->returnSymbol;
                                $this->useStructs[] = $word;
                            }

                            $type = $word;
                            $this->state = 'init';
                            $word = '';
                        }
                        else if(in_array($word,$this->preNamespaceEnums)) {
                            $type = 'short';
                            $this->state = 'init';
                            $word = '';
                        }
                        else if($this->isMap($word)) {
                            $mapVectorState = true;
                        }
                        else if($this->isVector($word)) {
                            $mapVectorState = true;
                        }
                        else {
                            // 读到了vector和map中间的空格,还没读完
                            if($mapVectorState) {
                                continue;
                            }
                            // 否则剩余的部分应该就是值和默认值
                            else {
                                if(!empty($word))
                                    $valueName = $word;
                                $this->state = 'init';
                                $word = '';
                            }
                        }
                    }
                    // 标志着map和vector的开始,不等到'>'的结束不罢休
                    // 这时候需要使用栈来push,然后一个个对应的pop,从而达到type的遍历
                    else if($char == '<') {
                        // 贪婪的向后,直到找出所有的'>'
                        $type = $word;
                        // 还会有一个wholeType,表示完整的部分
                        $mapVectorStack = [];
                        $wholeType = $type;
                        $wholeType .= '<';
                        array_push($mapVectorStack,'<');
                        while(!empty($mapVectorStack)) {
                            $moreChar = $param[$i+1];
                            $wholeType .= $moreChar;
                            if($moreChar == '<') {
                                array_push($mapVectorStack,'<');
                            }
                            else if($moreChar == '>') {
                                array_pop($mapVectorStack);
                            }
                            $i++;
                        }

                        $this->state = 'init';
                        $word = '';
                    }
                    else $word .= $char;
                }
            }


            if(!empty($word)) {
                $valueName = $word;
            }

            // echo "PPPParamType: ".$paramType." ||| type: ".$type." ||| extType:".$wholeType." ||| valueName:".$valueName."\n\n";
            if($paramType == 'in') {
                $inParams[] = [
                    'type' => $type,
                    'wholeType' => $wholeType,
                    'valueName' => $valueName
                ];
            }
            else {
                $outParams[] = [
                    'type' => $type,
                    'wholeType' => $wholeType,
                    'valueName' => $valueName
                ];
            }
        }

        return [
            'in' => $inParams,
            'out' => $outParams
        ];

    }

    public function returnParser($returnType)
    {
        if($this->isStruct($returnType)) {
            if(!in_array($returnType,$this->useStructs)) {
                $this->extraUse .= "use protocol\\".$this->namespaceName."\\classes\\".$returnType.";".$this->returnSymbol;
                $this->useStructs[] = $returnType;
            }
            $returnInfo = [
                'type' => $returnType,
                'wholeType' => $returnType,
                'valueName' => $returnType
            ];
            return $returnInfo;
        }
        else if( $this->isBasicType($returnType)) {
            $returnInfo = [
                'type' => $returnType,
                'wholeType' => $returnType,
                'valueName' => $returnType
            ];
            return $returnInfo;
        }

        $this->state = 'init';
        $word = '';
        $wholeType = '';
        $type = '';
        $mapVectorState = false;
        $valueName = '';

        for ($i = 0; $i < strlen($returnType) ; $i++) {
            $char = $returnType[$i];
            if($this->state == 'init') {
                // 有可能是换行
                if($this->isSpace($char)) {
                    continue;
                }
                else if($char == "\n") {
                    break;
                }
                else if($this->inIdentifier($char)) {
                    $this->state = 'identifier';
                    $word .= $char;
                }
                else {
                    $this->abnormalExit('error','Interface内格式错误,请更正jce');
                }
            }
            else if($this->state == 'identifier') {
                // 如果遇到了space,需要检查是不是在map或vector的类型中,如果当前积累的word并不合法
                // 并且又不是处在vector或map的前置状态下的话,那么就是出错了
                //echo "[debug][state={$this->state}]word:".$word."\n";
                if($this->isSpace($char)) {
                    if($this->isBasicType($word)) {
                        $type = $word;
                        $this->state = 'init';
                        $word = '';
                    }
                    else if($this->isStruct($word)) {

                        // 同时要把它增加到本Interface的依赖中
                        if(!in_array($word,$this->useStructs)) {
                            $this->extraUse .= "use protocol\\".$this->namespaceName."\\classes\\".$word.";".$this->returnSymbol;
                            $this->useStructs[] = $word;
                        }


                        $type = $word;
                        $this->state = 'init';
                        $word = '';
                    }
                    else if($this->isEnum($word)) {
                        $type = 'short';
                        $this->state = 'init';
                        $word = '';
                    }
                    else if(in_array($word,$this->preNamespaceStructs)) {
                        $word = explode("::",$word);
                        $word = $word[1];
                        // 同时要把它增加到本Interface的依赖中
                        if(!in_array($word,$this->useStructs)) {
                            $this->extraUse .= "use protocol\\".$this->namespaceName."\\classes\\".$word.";".$this->returnSymbol;
                            $this->useStructs[] = $word;
                        }

                        $type = $word;
                        $this->state = 'init';
                        $word = '';
                    }
                    else if(in_array($word,$this->preNamespaceEnums)) {
                        $type = 'short';
                        $this->state = 'init';
                        $word = '';
                    }
                    else if($this->isMap($word)) {
                        $mapVectorState = true;
                    }
                    else if($this->isVector($word)) {
                        $mapVectorState = true;
                    }
                    else {
                        // 读到了vector和map中间的空格,还没读完
                        if($mapVectorState) {
                            continue;
                        }
                        // 否则剩余的部分应该就是值和默认值
                        else {
                            if(!empty($word))
                                $valueName = $word;
                            $this->state = 'init';
                            $word = '';
                        }
                    }
                }
                // 标志着map和vector的开始,不等到'>'的结束不罢休
                // 这时候需要使用栈来push,然后一个个对应的pop,从而达到type的遍历
                else if($char == '<') {
                    // 贪婪的向后,直到找出所有的'>'
                    $type = $word;
                    // 还会有一个wholeType,表示完整的部分
                    $mapVectorStack = [];
                    $wholeType = $type;
                    $wholeType .= '<';
                    array_push($mapVectorStack,'<');
                    while(!empty($mapVectorStack)) {
                        $moreChar = $returnType[$i+1];
                        $wholeType .= $moreChar;
                        if($moreChar == '<') {
                            array_push($mapVectorStack,'<');
                        }
                        else if($moreChar == '>') {
                            array_pop($mapVectorStack);
                        }
                        $i++;
                    }

                    $this->state = 'init';
                    $word = '';
                }
                else $word .= $char;
            }
        }

        $returnInfo = [
            'type' => $type,
            'wholeType' => $wholeType,
            'valueName' => $valueName
        ];


        return $returnInfo;

    }

    /**
     * @param $tag
     * @param $requireType
     * @param $type
     * @param $name
     * @param $wholeType
     * @param $defaultValue
     */
    public function writeInterfaceLine($returnType,$funcName,$params) {
        $result = $this->paramParser($params);
        $inParams = $result['in'];
        $outParams = $result['out'];

        // 处理通用的头部
        $funcHeader = $this->generateFuncHeader($funcName,$inParams,$outParams);

        $returnInfo = $this->returnParser($returnType);

        $funcBodyArr = $this->generateFuncBody($inParams,$outParams,$returnInfo);
        $synFuncBody = $funcBodyArr['syn'];
        $asynFuncBody = $funcBodyArr['asyn'];


        $funcTail = $this->tabSymbol."}".$this->doubleReturn;

        $this->funcSet .= $funcHeader.$synFuncBody.$funcTail;
        $this->funcSetAs .= $funcHeader.$asynFuncBody.$funcTail;

    }

    private function paramTypeMap($paramType) {
        if($this->isBasicType($paramType) || $this->isMap($paramType) || $this->isVector($paramType)) {
            return "";
        }
        else {
            return $paramType;
        }
    }
    /**
     * @param $funcName
     * @param $inParams
     * @param $outParams
     * @return string
     */
    public function generateFuncHeader($funcName,$inParams,$outParams) {
        $paramsStr = "";
        foreach ($inParams as $param) {
            $paramPrefix = $this->paramTypeMap($param['type']);
            $paramSuffix = "$".$param['valueName'];
            $paramsStr .= !empty($paramPrefix)?$paramPrefix." ".$paramSuffix.",":$paramSuffix.",";

        }

        foreach ($outParams as $param) {
            $paramPrefix = $this->paramTypeMap($param['type']);
            $paramSuffix = "&$".$param['valueName'];
            $paramsStr .= !empty($paramPrefix)?$paramPrefix." ".$paramSuffix.",":$paramSuffix.",";
        }

        $paramsStr = trim($paramsStr,",");
        $paramsStr .= ") {".$this->returnSymbol;

        $funcHeader = $this->tabSymbol."public function ".$funcName."(".$paramsStr;

        return $funcHeader;
    }

    private function getPackMethods($type) {
        $packMethods = [
            'bool' => 'putBool',
            'byte' => 'putChar',
            'char' => 'putChar',
            'unsigned byte' => 'putUint8',
            'unsigned char' => 'putUint8',
            'short' => 'putShort',
            'unsigned short' => 'putUint16',
            'int' => 'putInt32',
            'unsigned int' => 'putUint32',
            'long' => 'putInt64',
            'float' => 'putFloat',
            'double' => 'putDouble',
            'string' => 'putString',
            'enum' => 'putShort',
            'map' => 'putMap',
            'vector' => 'putVector',
            'Bool' => 'putBool',
            'Byte' => 'putChar',
            'Char' => 'putChar',
            'Unsigned byte' => 'putUint8',
            'Unsigned char' => 'putUint8',
            'Short' => 'putShort',
            'Unsigned short' => 'putUint16',
            'Int' => 'putInt32',
            'Unsigned int' => 'putUint32',
            'Long' => 'putInt64',
            'Float' => 'putFloat',
            'Double' => 'putDouble',
            'String' => 'putString',
            'Enum' => 'putShort',
            'Map' => 'putMap',
            'Vector' => 'putVector'
        ];

        if(isset($packMethods[$type]))
            return $packMethods[$type];
        else return 'putStruct';
    }

    private function getUnpackMethods($type) {
        $unpackMethods = [
            'bool' => 'getBool',
            'byte' => 'getChar',
            'char' => 'getChar',
            'unsigned byte' => 'getUint8',
            'unsigned char' => 'getUint8',
            'short' => 'getShort',
            'unsigned short' => 'getUint16',
            'int' => 'getInt32',
            'unsigned int' => 'getUint32',
            'long' => 'getInt64',
            'float' => 'getFloat',
            'double' => 'getDouble',
            'string' => 'getString',
            'enum' => 'getShort',
            'map' => 'getMap',
            'vector' => 'getVector',
            'Bool' => 'getBool',
            'Byte' => 'getChar',
            'Char' => 'getChar',
            'Unsigned byte' => 'getUint8',
            'Unsigned char' => 'getUint8',
            'Short' => 'getShort',
            'Unsigned short' => 'getUint16',
            'Int' => 'getInt32',
            'Unsigned int' => 'getUint32',
            'Long' => 'getInt64',
            'Float' => 'getFloat',
            'Double' => 'getDouble',
            'String' => 'getString',
            'Enum' => 'getShort',
            'Map' => 'getMap',
            'Vector' => 'getVector'
        ];


        if(isset($unpackMethods[strtolower($type)]))
            return $unpackMethods[strtolower($type)];
        else if($this->isEnum($type)) {
            return 'getShort';
        }
        else return 'getStruct';
    }

    /**
     * @param $funcName
     * @param $inParams
     * @param $outParams
     * 生成函数的包体
     */
    public function generateFuncBody($inParams,$outParams,$returnInfo) {
        $bodyPrefix = $this->doubleTab."if(\$this->_iVersion === 1) \n".
            $this->tripleTab."\$this->_tafAssistant->setRequest(\$this->_servantName,__FUNCTION__,\$this->_ip,\$this->_port,\$this->_socketMode,\$this->_iVersion);\n".
            $this->doubleTab."else \n".
            $this->tripleTab."\$this->_tafAssistant->setRequest(\$this->_servantName,__FUNCTION__,\$this->_ip,\$this->_port);".$this->doubleReturn.
            $this->doubleTab."try {".$this->returnSymbol;

        $bodyPrefixAs = $this->doubleTab."if(\$this->_iVersion === 1) \n".
            $this->tripleTab."(yield \$this->_tafAssistant->setRequest(\$this->_servantName,__FUNCTION__,\$this->_ip,\$this->_port,\$this->_socketMode,\$this->_iVersion));\n".
            $this->doubleTab."else \n".
            $this->tripleTab."(yield \$this->_tafAssistant->setRequest(\$this->_servantName,__FUNCTION__,\$this->_ip,\$this->_port));".$this->doubleReturn.
            $this->doubleTab."try {".$this->returnSymbol;


        /* old logic
               $bodyPrefix = $this->doubleTab."\$this->_tafAssistant->setRequest(\$this->_servantName,__FUNCTION__,\$this->_ip,\$this->_port);".$this->doubleReturn.
        //            $this->doubleTab."try {".$this->returnSymbol;
        //
        //        $bodyPrefixAs = $this->doubleTab.
        //            "(yield \$this->_tafAssistant->setRequest(\$this->_servantName,__FUNCTION__,\$this->_ip,\$this->_port));".$this->doubleReturn.
        //            $this->doubleTab."try {".$this->returnSymbol;*/

        $bodySuffix = $this->doubleTab."catch (\\Exception \$e) {".$this->returnSymbol.
            $this->tripleTab."return array(".$this->returnSymbol.
            $this->quardupleTab."\"code\" => \$e->getCode(),".$this->returnSymbol.
            $this->quardupleTab."\"msg\" => \$e->getMessage(),".$this->returnSymbol.
            $this->tripleTab.");".$this->returnSymbol.
            $this->doubleTab."}".$this->returnSymbol;


        $bodySuffixAs = $this->doubleTab."catch (\\Exception \$e) {".$this->returnSymbol.
            $this->tripleTab."yield array(".$this->returnSymbol.
            $this->quardupleTab."\"code\" => \$e->getCode(),".$this->returnSymbol.
            $this->quardupleTab."\"msg\" => \$e->getMessage(),".$this->returnSymbol.
            $this->tripleTab.");".$this->returnSymbol.
            $this->doubleTab."}".$this->returnSymbol;

        $bodyMiddle = "";


        $commonPrefix = "\$this->_tafAssistant->";
        $curTag = 1;
        foreach ($inParams as $param) {
            $type = $param['type'];

            $packMethod = $this->getPackMethods($type);
            $valueName = $param['valueName'];


            // 判断如果是vector需要特别的处理
            if($this->isVector($type)) {
                $vecFill = $this->tripleTab."\$".$valueName."_vec = ".$this->getExtType($param['wholeType']).";".$this->returnSymbol.
                    $this->tripleTab."foreach(\$".$valueName." as "."\$single".$valueName.") {".$this->returnSymbol.
                    $this->quardupleTab."\$".$valueName."_vec->pushBack(\$single".$valueName.");".$this->returnSymbol.
                    $this->tripleTab."}".$this->doubleReturn;
                $bodyMiddle .= $vecFill;
                // old logic
                // $bodyMiddle .= $this->tripleTab.$commonPrefix.$packMethod."(\"".$valueName."\",\$".$valueName."_vec);".$this->returnSymbol;
                $bodyMiddle .= $this->tripleTab."if(\$this->_iVersion === 1)\n".
                    $this->quardupleTab.$commonPrefix.$packMethod."(".$curTag.",\$".$valueName."_vec);\n".
                    $this->tripleTab."else \n".
                    $this->quardupleTab.$commonPrefix.$packMethod."(\"".$valueName."\",\$".$valueName."_vec);\n";
            }

            // 判断如果是map需要特别的处理
            else if($this->isMap($type)) {

                $mapFill = $this->tripleTab."\$".$valueName."_map = ".$this->getExtType($param['wholeType']).";".$this->returnSymbol.
                    $this->tripleTab."foreach(\$".$valueName." as "."\$single".$valueName.") {".$this->returnSymbol.
                    $this->quardupleTab."\$".$valueName."_map->pushBack(\$single".$valueName.");".$this->returnSymbol.
                    $this->tripleTab."}".$this->doubleReturn;
                $bodyMiddle .= $mapFill;
                // old logic
                // $bodyMiddle .= $this->tripleTab.$commonPrefix.$packMethod."(\"".$valueName."\",\$".$valueName."_map);".$this->returnSymbol;
                $bodyMiddle .= $this->tripleTab."if(\$this->_iVersion === 1)\n".
                    $this->quardupleTab.$commonPrefix.$packMethod."(".$curTag.",\$".$valueName."_map);\n".
                    $this->tripleTab."else \n".
                    $this->quardupleTab.$commonPrefix.$packMethod."(\"".$valueName."\",\$".$valueName."_map);\n";
            }
            // 针对struct,需要额外的use过程
            else if($this->isStruct($type)) {
                if(!in_array($type,$this->useStructs)) {
                    $this->extraUse .= "use protocol\\".$this->namespaceName."\\classes\\".$param['type'].";".$this->returnSymbol;
                    $this->useStructs[] = $param['type'];
                }
                // old logic
                // $bodyMiddle .= $this->tripleTab.$commonPrefix.$packMethod."(\"".$valueName."\",\$".$valueName.");".$this->returnSymbol;

                $bodyMiddle .= $this->tripleTab."if(\$this->_iVersion === 1)\n".
                    $this->quardupleTab.$commonPrefix.$packMethod."(".$curTag.",\$".$valueName.");\n".
                    $this->tripleTab."else \n".
                    $this->quardupleTab.$commonPrefix.$packMethod."(\"".$valueName."\",\$".$valueName.");\n";
            }

            else    {
                //$bodyMiddle .= $this->tripleTab.$commonPrefix.$packMethod."(\"".$valueName."\",\$".$valueName.");".$this->returnSymbol;
                $bodyMiddle .= $this->tripleTab."if(\$this->_iVersion === 1)\n".
                    $this->quardupleTab.$commonPrefix.$packMethod."(".$curTag.",\$".$valueName.");\n".
                    $this->tripleTab."else \n".
                    $this->quardupleTab.$commonPrefix.$packMethod."(\"".$valueName."\",\$".$valueName.");\n";
            }


            $curTag++;
        }

        $bodyMiddle .= $this->doubleReturn;

        $bodyMiddleAs = $bodyMiddle;

        // 判断是否是异步的,进行分别的处理
        $bodyMiddle .= $this->tripleTab."\$this->_tafAssistant->sendAndReceive();".$this->doubleReturn;
        $bodyMiddleAs .= $this->tripleTab."(yield \$this->_tafAssistant->sendAndReceive());".$this->doubleReturn;


        foreach ($outParams as $param) {

            $type = $param['type'];

            $unpackMethods = $this->getUnpackMethods($type);
            $name = $param['valueName'];

            if($this->isBasicType($type)) {
                $bodyMiddle .= $this->tripleTab."if(\$this->_iVersion === 1)\n".
                    $this->quardupleTab."\$$name = ".$commonPrefix.$unpackMethods."(".$curTag.");".$this->returnSymbol.
                    $this->tripleTab."else \n".
                    $this->quardupleTab."\$$name = ".$commonPrefix.$unpackMethods."(\"".$name."\");".$this->returnSymbol;

                $bodyMiddleAs .= $this->tripleTab."if(\$this->_iVersion === 1)\n".
                    $this->quardupleTab."\$$name = ".$commonPrefix.$unpackMethods."(".$curTag.");".$this->returnSymbol.
                    $this->tripleTab."else \n".
                    $this->quardupleTab."\$$name = ".$commonPrefix.$unpackMethods."(\"".$name."\");".$this->returnSymbol;

                // old logic
                // $bodyMiddle .= $this->tripleTab."\$$name = ".$commonPrefix.$unpackMethods."(\"".$name."\");".$this->returnSymbol;
                // $bodyMiddleAs .= $this->tripleTab."\$$name = ".$commonPrefix.$unpackMethods."(\"".$name."\");".$this->returnSymbol;
            }
            else {
                // 判断如果是vector需要特别的处理
                if($this->isVector($type) || $this->isMap($type)) {
                    $bodyMiddle .= $this->tripleTab."if(\$this->_iVersion === 1)\n".
                        $this->quardupleTab."\$$name = ".$commonPrefix.$unpackMethods."(".$curTag.",".$this->getExtType($param['wholeType']).");".$this->returnSymbol.
                        $this->tripleTab."else \n".
                        $this->quardupleTab."\$$name = ".$commonPrefix.$unpackMethods."(\"".$name."\",".$this->getExtType($param['wholeType']).");".$this->returnSymbol;

                    $bodyMiddleAs .= $this->tripleTab."if(\$this->_iVersion === 1)\n".
                        $this->quardupleTab."\$$name = ".$commonPrefix.$unpackMethods."(".$curTag.",".$this->getExtType($param['wholeType']).");".$this->returnSymbol.
                        $this->tripleTab."else \n".
                        $this->quardupleTab."\$$name = ".$commonPrefix.$unpackMethods."(\"".$name."\",".$this->getExtType($param['wholeType']).");".$this->returnSymbol;


                    // old logic
                    //$bodyMiddle .= $this->tripleTab."\$$name = ".$commonPrefix.$unpackMethods."(\"".$name."\",".$this->getExtType($param['wholeType']).");".$this->returnSymbol;
                    //$bodyMiddleAs .= $this->tripleTab."\$$name = ".$commonPrefix.$unpackMethods."(\"".$name."\",".$this->getExtType($param['wholeType']).");".$this->returnSymbol;
                }
                // 如果是struct
                else if($this->isStruct($type)) {
                    $bodyMiddle .= $this->tripleTab."if(\$this->_iVersion === 1)\n".
                        $this->quardupleTab."\$ret = ".$commonPrefix.$unpackMethods."(".$curTag.",\$$name);".$this->returnSymbol.
                        $this->tripleTab."else \n".
                        $this->quardupleTab."\$ret = ".$commonPrefix.$unpackMethods."(\"".$name."\",\$$name);".$this->returnSymbol;

                    $bodyMiddleAs .= $this->tripleTab."if(\$this->_iVersion === 1)\n".
                        $this->quardupleTab."\$ret = ".$commonPrefix.$unpackMethods."(".$curTag.",\$$name);".$this->returnSymbol.
                        $this->tripleTab."else \n".
                        $this->quardupleTab."\$ret = ".$commonPrefix.$unpackMethods."(\"".$name."\",\$$name);".$this->returnSymbol;



                    // old logic
                    //$bodyMiddle .= $this->tripleTab."\$ret = ".$commonPrefix.$unpackMethods."(\"".$name."\",\$$name);".$this->returnSymbol;
                    //$bodyMiddleAs .= $this->tripleTab."\$ret = ".$commonPrefix.$unpackMethods."(\"".$name."\",\$$name);".$this->returnSymbol;

                    if (!in_array($type, $this->useStructs)) {
                        $this->extraUse .= "use protocol\\" . $this->namespaceName . "\\classes\\" . $param['type'] . ";" . $this->returnSymbol;
                        $this->useStructs[] = $param['type'];
                    }
                }
            }

            $curTag++;
        }



        // 还要尝试去获取一下接口的返回码哦
        $returnUnpack = $this->getUnpackMethods($returnInfo['type']);
        $valueName = $returnInfo['valueName'];

        if($returnInfo['type'] !== 'void') {
            if($this->isVector($returnInfo['type']) || $this->isMap($returnInfo['type'])) {
                $bodyMiddle .= $this->tripleTab."if(\$this->_iVersion === 1)\n".
                    $this->quardupleTab."return \$this->_tafAssistant->".$returnUnpack."(0," .
                    $this->getExtType($returnInfo['wholeType']).");".$this->doubleReturn.
                    $this->tripleTab."else \n".
                    $this->quardupleTab."return \$this->_tafAssistant->".$returnUnpack."(\"\"," .
                    $this->getExtType($returnInfo['wholeType']).");".$this->doubleReturn.
                    $this->doubleTab."}".$this->returnSymbol;

                $bodyMiddleAs .= $this->tripleTab."if(\$this->_iVersion === 1){\n".
                    $this->quardupleTab."\$ret = \$this->_tafAssistant->".$returnUnpack."(0," .
                    $this->getExtType($returnInfo['wholeType']).");".$this->doubleReturn.
                    $this->quardupleTab."yield \$ret;}".$this->returnSymbol.
                    $this->tripleTab."else{ \n".
                    $this->quardupleTab."\$ret = \$this->_tafAssistant->".$returnUnpack."(\"\"," .
                    $this->getExtType($returnInfo['wholeType']).");".$this->doubleReturn.
                    $this->quardupleTab."yield \$ret;}".$this->returnSymbol.
                    $this->doubleTab."}".$this->returnSymbol;


                /* old logic
                 * $bodyMiddle .= $this->tripleTab."return \$this->_tafAssistant->".$returnUnpack."(\"\","
                    .$this->getExtType($returnInfo['wholeType']).");".$this->doubleReturn.
                    $this->doubleTab."}".$this->returnSymbol;

                $bodyMiddleAs .= $this->tripleTab."\$ret = \$this->_tafAssistant->".$returnUnpack."(\"\","
                    .$this->getExtType($returnInfo['wholeType']).");".$this->returnSymbol.
                    $this->tripleTab."yield \$ret;".$this->returnSymbol.
                    $this->doubleTab."}".$this->returnSymbol;*/
            }
            else if($this->isStruct($returnInfo['type'])) {
                $bodyMiddle .= $this->tripleTab."\$returnVal = new $valueName();".$this->returnSymbol;

                $bodyMiddle .= $this->tripleTab."if(\$this->_iVersion === 1)\n".
                    $this->quardupleTab."\$this->_tafAssistant->".$returnUnpack."(0,\$returnVal);".$this->returnSymbol.
                    $this->tripleTab."else\n".
                    $this->quardupleTab."\$this->_tafAssistant->".$returnUnpack."(\"\",\$returnVal);".$this->returnSymbol;

                // old logic
                //$bodyMiddle .= $this->tripleTab."\$this->_tafAssistant->".$returnUnpack."(\"\",\$returnVal);".$this->returnSymbol;

                $bodyMiddle .= $this->tripleTab."return \$returnVal;".$this->doubleReturn.
                    $this->doubleTab."}".$this->returnSymbol;

                $bodyMiddleAs .= $this->tripleTab."\$returnVal = new $valueName();".$this->returnSymbol;

                $bodyMiddleAs .= $this->tripleTab."if(\$this->_iVersion === 1)\n".
                    $this->quardupleTab."\$this->_tafAssistant->".$returnUnpack."(0,\$returnVal);".$this->returnSymbol.
                    $this->tripleTab."else\n".
                    $this->quardupleTab."\$this->_tafAssistant->".$returnUnpack."(\"\",\$returnVal);".$this->returnSymbol;

                // old logic
                //$bodyMiddleAs .= $this->tripleTab."\$this->_tafAssistant->".$returnUnpack."(\"\",\$returnVal);".$this->returnSymbol;

                $bodyMiddleAs .= $this->tripleTab."yield \$returnVal;".$this->doubleReturn.
                    $this->doubleTab."}".$this->returnSymbol;


                if (!in_array($returnInfo['type'], $this->useStructs)) {
                    $this->extraUse .= "use protocol\\" . $this->namespaceName . "\\classes\\" . $returnInfo['type'] . ";" . $this->returnSymbol;
                    $this->useStructs[] = $returnInfo['type'];
                }
            }
            else {
                $bodyMiddle .= $this->tripleTab."if(\$this->_iVersion === 1)\n".
                    $this->quardupleTab."return \$this->_tafAssistant->".$returnUnpack."(0);".$this->returnSymbol.
                    $this->tripleTab."else\n".
                    $this->quardupleTab."return \$this->_tafAssistant->".$returnUnpack."(\"\");".$this->returnSymbol.
                    $this->doubleTab."}".$this->returnSymbol;


                $bodyMiddleAs .= $this->tripleTab."if(\$this->_iVersion === 1){\n".
                    $this->quardupleTab."\$ret = \$this->_tafAssistant->".$returnUnpack."(0);".$this->returnSymbol.
                    $this->tripleTab."yield \$ret;}".$this->returnSymbol.
                    $this->tripleTab."else{\n".
                    $this->quardupleTab."\$ret = \$this->_tafAssistant->".$returnUnpack."(\"\");".$this->returnSymbol.
                    $this->tripleTab."yield \$ret;}".$this->returnSymbol.
                    $this->doubleTab."}".$this->returnSymbol;



                /* old logic
                 * $bodyMiddle .= $this->tripleTab."return \$this->_tafAssistant->".$returnUnpack."(\"\");".$this->doubleReturn.
                    $this->doubleTab."}".$this->returnSymbol;

                $bodyMiddleAs .= $this->tripleTab."\$ret = \$this->_tafAssistant->".$returnUnpack."(\"\");".$this->returnSymbol.
                    $this->tripleTab."yield \$ret;".$this->returnSymbol.
                    $this->doubleTab."}".$this->returnSymbol;*/
            }
        }
        else {
            $bodyMiddle .= $this->doubleTab."}".$this->returnSymbol;
            $bodyMiddleAs .=
                $this->tripleTab."yield;".$this->returnSymbol.
                $this->doubleTab."}".$this->returnSymbol;
        }


        $bodyStr = $bodyPrefix.$bodyMiddle.$bodySuffix;
        $bodyStrAs = $bodyPrefixAs.$bodyMiddleAs.$bodySuffixAs;

        return [
            'asyn' => $bodyStrAs,
            'syn' => $bodyStr
        ];
    }

}

class FileConverter
{
    public $moduleName;
    public $uniqueName;
    public $interfaceName;
    public $fromFile;
    public $outputDir;


    public $servantName;

    public $namespaceName;

    public $preStructs=[];
    public $preEnums=[];
    public $preConsts=[];
    public $preNamespaceEnums=[];
    public $preNamespaceStructs=[];

    public function __construct($fromFile, $servantName,$outputDir='',$inputDir='')
    {
        $this->fromFile = $fromFile;
        $this->servantName = $servantName;
        $this->outputDir = $outputDir;
        $this->inputDir = $inputDir;
        $this->initDir();
    }

    /**
     * 首先需要初始化一些文件目录
     * @return [type] [description]
     */
    public function initDir() {
        $moduleElements = explode(".",$this->servantName);

        $product = $moduleElements[0];
        $project = $moduleElements[1];
        $service = $moduleElements[2];

        if (strtolower(substr(php_uname('a'), 0, 3)) === 'win') {
            exec("mkdir " .$this->outputDir. $product);
            exec("mkdir " .$this->outputDir. $product . "\\" . $project);
            exec("DEL " .$this->outputDir. $product . "\\" . $project . "\\" . $service . "\\*.*");
            exec("mkdir " .$this->outputDir. $product . "\\" . $project . "\\" . $service);

            $this->moduleName = $product . "\\" . $project . "\\" . $service;

            exec("mkdir " .$this->outputDir. $this->moduleName . "\\classes");
            exec("mkdir " .$this->outputDir. $this->moduleName . "\\jce");
            exec("copy " . $this->fromFile . " " .$this->outputDir. $this->moduleName . "\\jce");

            $this->namespaceName = $product . "\\" . $project . "\\" . $service;

            $this->uniqueName = $product . "_" . $project . "_" . $service;
            return;
        }

        exec("mkdir ".$this->outputDir.$product);
        exec("mkdir ".$this->outputDir.$product."/".$project);
        exec("rm -rf ". $this->outputDir. $product."/".$project."/".$service);
        exec("mkdir ".$this->outputDir.$product."/".$project."/".$service);

        $this->moduleName = $product."/".$project."/".$service;

        exec("mkdir ".$this->outputDir.$this->moduleName."/classes");
        exec("mkdir ".$this->outputDir.$this->moduleName."/jce");
        exec("cp ".$this->fromFile." ".$this->outputDir.$this->moduleName."/jce");

        $this->namespaceName = $product."\\".$project."\\".$service;

        $this->uniqueName = $product."_".$project."_".$service;
    }

    public function usage()
    {
        echo 'php jce2php.php $jce_file $servantName';
    }


    public function abnormalExit($level,$msg) {
        echo "[$level]$msg,行号\n";
        exit;
    }

    public function moduleScan()
    {
        $fp = fopen($this->fromFile, 'r');
        if (!$fp) {
            $this->usage();
            exit;
        }
        while (($line = fgets($fp, 1024)) !== false) {

            // 判断是否有module
            $moduleFlag = strpos($line,"module");
            if($moduleFlag !== false) {
                $name = Utils::pregMatchByName("module",$line);
                $currentModule = $name;
            }

            // 判断是否有include
            $includeFlag = strpos($line,"#include");
            if($includeFlag !== false) {
                // 找出jce对应的文件名
                $tokens = preg_split("/#include/", $line);
                $includeFile = trim($tokens[1],"\" \r\n");

                if (strtolower(substr(php_uname('a'), 0, 3)) === 'win') {
                    exec("copy " . $this->inputDir.$includeFile . " " . $this->outputDir.$this->moduleName . "\\jce");
                }else {
                    exec("cp " . $this->inputDir.$includeFile . " " . $this->outputDir.$this->moduleName . "/jce");
                }

                $includeParser = new IncludeParser();
                $includeParser->includeScan($this->inputDir.$includeFile,$this->preEnums,$this->preStructs,
                    $this->preNamespaceEnums,$this->preNamespaceStructs);
            }

            // 如果空行，或者是注释，就直接略过
            if (!trim($line) || trim($line)[0] === '/' || trim($line)[0] === '*') {
                continue;
            }

            // 正则匹配,发现是在enum中
            $enumFlag = strpos($line,"enum");
            if($enumFlag !== false) {
                $name = Utils::pregMatchByName("enum",$line);
                $this->preEnums[] = $name;

                // 增加命名空间以备不时之需
                if(!empty($currentModule))
                    $this->preNamespaceEnums[] = $currentModule."::".$name;

                while(($lastChar = fgetc($fp)) != '}') {
                    continue;
                }
            }

            // 正则匹配，发现是在结构体中
            $structFlag = strpos($line, "struct");
            // 一旦发现了struct，那么持续读到结束为止
            if ($structFlag !== false) {
                $name = Utils::pregMatchByName("struct",$line);

                $this->preStructs[] = $name;
                // 增加命名空间以备不时之需
                if(!empty($currentModule))
                    $this->preNamespaceStructs[] = $currentModule."::".$name;
            }
        }
        fclose($fp);
    }

    public function moduleParse()
    {
        $fp = fopen($this->fromFile, 'r');
        if (!$fp) {
            $this->usage();
        }
        while (($line = fgets($fp, 1024)) !== false) {

            // 判断是否有include
            $includeFlag = strpos($line,"#include");
            if($includeFlag !== false) {
                // 找出jce对应的文件名
                $tokens = preg_split("/#include/", $line);
                $includeFile = trim($tokens[1],"\" \r\n");
                $includeParser = new IncludeParser();
                $includeParser->includeParse($this->inputDir.$includeFile,$this->preEnums,$this->preStructs,$this->uniqueName,
                    $this->moduleName,$this->namespaceName,$this->servantName,$this->preNamespaceEnums,$this->preNamespaceStructs,$this->outputDir);
            }

            //echo "Outter line num:"." line:".$line."\n";
            // 如果空行，或者是注释，就直接略过
            if (!trim($line) || trim($line)[0] === '/' || trim($line)[0] === '*') {
                continue;
            }

            // 正则匹配,发现是在enum中
            $enumFlag = strpos($line,"enum");
            if($enumFlag !== false) {
                // 处理第一行,正则匹配出classname
                $enumTokens = preg_split('/enum/', $line);

                $enumName = $enumTokens[1];
                $enumName = trim($enumName," \r\0\x0B\t\n{");

                // 判断是否是合法的structName
                preg_match('/[a-zA-Z][0-9a-zA-Z]/',$enumName,$matches);
                if(empty($matches)) {
                    $this->abnormalExit('error','Enum名称有误');
                }

                $this->preEnums[] = $enumName;
                while(($lastChar = fgetc($fp)) != '}') {
                    continue;
                }

                // echo " after enum {$enumName}:";
            }


            // 正则匹配，发现是在结构体中
            $structFlag = strpos($line, "struct");
            // 一旦发现了struct，那么持续读到结束为止
            if ($structFlag !== false) {

                $structTokens = preg_split('/struct/', $line);


                $structName = $structTokens[1];
                $structName = trim($structName," \r\0\x0B\t\n{");

                // 判断是否是合法的structName
                preg_match('/[a-zA-Z][0-9a-zA-Z]/',$structName,$matches);
                if(empty($matches)) {
                    $this->abnormalExit('error','Struct名称有误');
                }

                $this->preStructs[] = $structName;

                $structParser = new StructParser($fp,$line,$this->uniqueName,$this->moduleName,$structName,$this->preStructs,
                    $this->preEnums,$this->namespaceName,$this->preNamespaceEnums,$this->preNamespaceStructs);
                $structClassStr = $structParser->parse();
                file_put_contents($this->outputDir.$this->moduleName."/classes/".$structName.".php", $structClassStr);

            }

            // 正则匹配，发现是在interface中 todo
            $interfaceFlag = strpos(strtolower($line), "interface");
            // 一旦发现了struct，那么持续读到结束为止
            if ($interfaceFlag !== false) {
                $interfaceTokens = preg_split('/interface/', $line);


                $interfaceName = $interfaceTokens[1];
                $interfaceName = trim($interfaceName," \r\0\x0B\t\n{");

                // 判断是否是合法的structName
                preg_match('/[a-zA-Z][0-9a-zA-Z]/',$interfaceName,$matches);
                if(empty($matches)) {
                    $this->abnormalExit('error','Interface名称有误');
                }

                if(in_array($interfaceName,$this->preStructs)) {
                    $interfaceName .= "Servant";
                }

                $asInterfaceName = $interfaceName."As";
                $nyInterfaceName = $interfaceName.'Ny'; //swoole2.0 no yield version
                $interfaceParser = new InterfaceParser($fp, $line, $this->namespaceName, $this->moduleName,
                    $interfaceName, $asInterfaceName, $nyInterfaceName, $this->preStructs,
                    $this->preEnums, $this->servantName, $this->preNamespaceEnums, $this->preNamespaceStructs);
                $interfaces = $interfaceParser->parse();

                // 需要区分同步和异步的两种方式 todo
                file_put_contents($this->outputDir.$this->moduleName."/".$interfaceName.".php", $interfaces['syn']);
                file_put_contents($this->outputDir.$this->moduleName."/".$asInterfaceName.".php", $interfaces['asyn']);
                file_put_contents($this->outputDir . $this->moduleName . "/" . $nyInterfaceName . ".php",
                    $interfaces['ny']);

            }
        }
    }
}

class IncludeParser {

    public function includeScan($includeFile,&$preEnums,&$preStructs,
                                &$preNamespaceEnums,&$preNamespaceStructs)
    {
        $fp = fopen($includeFile, 'r');
        if (!$fp) {
            echo "Include file not exit, please check";
            exit;
        }
        while (($line = fgets($fp, 1024)) !== false) {
            // 如果空行，或者是注释，就直接略过
            if (!trim($line) || trim($line)[0] === '/' || trim($line)[0] === '*') {
                continue;
            }

            // 判断是否有module
            $moduleFlag = strpos($line,"module");
            if($moduleFlag !== false) {
                $name = Utils::pregMatchByName("module",$line);
                $currentModule = $name;
            }

            // 正则匹配,发现是在enum中
            $enumFlag = strpos($line,"enum");
            if($enumFlag !== false) {
                $name = Utils::pregMatchByName("enum",$line);
                $preEnums[] = $name;
                if(!empty($currentModule))
                    $preNamespaceEnums[] = $currentModule."::".$name;
                while(($lastChar = fgetc($fp)) != '}') {
                    continue;
                }
            }

            // 正则匹配，发现是在结构体中
            $structFlag = strpos($line, "struct");
            // 一旦发现了struct，那么持续读到结束为止
            if ($structFlag !== false) {
                $name = Utils::pregMatchByName("struct",$line);

                $preStructs[] = $name;
                if(!empty($currentModule))
                    $preNamespaceStructs[] = $currentModule."::".$name;
            }
        }
    }

    public function includeParse($includeFile,$preEnums,$preStructs,
                                 $uniqueName,$moduleName,$namespaceName,
                                 $servantName,$preNamespaceEnums,$preNamespaceStructs,$outputDir)
    {
        $fp = fopen($includeFile, 'r');
        if (!$fp) {
            echo "Include file not exit, please check";
            exit;
        }
        while (($line = fgets($fp, 1024)) !== false) {
            // 如果空行，或者是注释，就直接略过
            if (!trim($line) || trim($line)[0] === '/' || trim($line)[0] === '*') {
                continue;
            }
            // 正则匹配,发现是在consts中
            $constFlag = strpos($line,"const");
            if($constFlag !== false) {
                // 直接进行正则匹配
                Utils::abnormalExit('warning','const is not supported, please make sure you deal with them yourself in this version!');
            }

            // 正则匹配，发现是在结构体中
            $structFlag = strpos($line, "struct");
            // 一旦发现了struct，那么持续读到结束为止
            if ($structFlag !== false) {
                $name = Utils::pregMatchByName("struct",$line);

                $structParser = new StructParser($fp,$line,$uniqueName,$moduleName,$name,$preStructs,
                    $preEnums,$namespaceName,$preNamespaceEnums,$preNamespaceStructs);
                $structClassStr = $structParser->parse();
                file_put_contents($outputDir.$moduleName."/classes/".$name.".php", $structClassStr);

            }

            // 正则匹配，发现是在interface中
            $interfaceFlag = strpos(strtolower($line), "interface");
            // 一旦发现了struct，那么持续读到结束为止
            if ($interfaceFlag !== false) {
                $name = Utils::pregMatchByName("interface",$line);

                if(in_array($name,$preStructs)) {
                    $name .= "Servant";
                }
                $asName = $name."As";

                $interfaceParser = new InterfaceParser($fp,$line,$namespaceName,$moduleName,
                    $name,$asName,$preStructs,$preEnums,$servantName,$preNamespaceEnums,$preNamespaceStructs);
                $interfaces = $interfaceParser->parse();

                // 需要区分同步和异步的两种方式
                file_put_contents($outputDir.$moduleName."/".$name.".php", $interfaces['syn']);
            }
        }
    }
}


$fileConverter = new FileConverter($fromFile,$servantName,$outputDir,$inputDir);

$fileConverter->moduleScan();

$fileConverter->moduleParse();






