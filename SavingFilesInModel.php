<?php

namespace oshchyp\SavingFilesInModel;

use Yii;
use yii\base\Component;
use yii\web\UploadedFile;

class SavingFilesInModel extends Component
{
    private $_model;

    private $_rules;

    const PATH_FORMATION_SERVER = 1;

    const PATH_FORMATION_WEB = 2;

    public $eventSetAttributes = \yii\db\ActiveRecord::EVENT_BEFORE_VALIDATE;

    public $eventSaveFiles = \yii\db\ActiveRecord::EVENT_AFTER_VALIDATE;

    public $eventSetNamesFiles = \yii\db\ActiveRecord::EVENT_AFTER_VALIDATE;

    public $eventValidate = null;

    public function load($model, $attributesValues = [])
    {
        $this->_model = $model;
        $this->_model->username = 'test';
        if ($attributesValues) {
            foreach ($attributesValues as $k => $v) {
                $this->$k = $v;
            }
        }
        $this->_loadRules();

        return $this;
    }

    public function setEvents($model = null)
    {
        //  dump($this->_model, 1);
        $this->_model->on($this->eventSetAttributes, function () {
            $this->setAttributesFiles();
        });

        if ($this->eventValidate) {
            $this->_model->on($this->eventValidate, function () {
                $this->_model->validate();
            });
        }

        $this->_model->on($this->eventSaveFiles, function () {
            if (!$this->_model->getErrors()) {
                $this->saveFiles();
            }
        });

        $this->_model->on($this->eventSetNamesFiles, function () {
            if (!$this->_model->getErrors()) {
                $this->setNamesFiles();
            }
        });

        return $this->_model;
    }

    public function _loadRules()
    {
        $rules = $this->_model->fileAttributesRule();
        $ruleStructure = [
            'nameTo' => '', 'rootDir' => '', 'dir' => '', 'name' => function ($fileObj, $attr) {
                return uniqid().'-'.$fileObj->baseName.'.'.$fileObj->extension;
            },
        ];
        foreach ($rules as $rul) {
            foreach ($ruleStructure as $rsKey => $rsVal) {
                $rulData[$rsKey] = isset($rul[$rsKey]) ? $rul[$rsKey] : $rsVal;
            }
            if (is_string($rul[0])) {
                $this->_rules[$rul[0]] = $rulData;
            } else {
                foreach ($rul[0] as $attrName) {
                    $this->_rules[$attrName] = $rulData;
                }
            }
        }
        // dump($this->_rules, 1);
    }

    public function getName($attr, $fileObj)
    {
        if (!isset($this->_rules[$attr]['realName'])) {
            $this->_rules[$attr]['realName'] = $this->_rules[$attr]['name']($fileObj, $attr);
        }

        return $this->_rules[$attr]['realName'];
    }

    public function pathFormation($rule, $type)
    {
        $path = '';
        if ($type == static::PATH_FORMATION_SERVER) {
            $path = Yii::getAlias('@app');
            if ($rule['rootDir']) {
                $path .= '/'.$rule['rootDir'];
            }
        }
        if ($rule['dir']) {
            $path .= '/'.$rule['dir'];
        }

        return $path;
    }

    public function setAttributesFiles()
    {
        foreach ($this->_rules as $k => $v) {
            $this->_model->$k = UploadedFile::getInstance($this->_model, $k);
        }
    }

    public function saveFiles()
    {
        foreach ($this->_rules as $k => $v) {
            if ($this->_model->$k) {
                $filePath = $this->pathFormation($v, static::PATH_FORMATION_SERVER).'/'.$this->getName($k, $this->_model->$k);
                $this->_model->$k->saveAs($filePath);
            }
        }
    }

    public function setNamesFiles()
    {
        foreach ($this->_rules as $k => $v) {
            if ($v['nameTo'] && $this->_model->$k) {
                $attr = $v['nameTo'];
                $this->_model->$attr = $this->pathFormation($v, static::PATH_FORMATION_WEB).'/'.$this->getName($k, $this->_model->$k);
            }
        }
    }
}
