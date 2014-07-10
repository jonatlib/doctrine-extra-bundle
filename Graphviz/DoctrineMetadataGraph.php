<?php

namespace Alex\DoctrineExtraBundle\Graphviz;

use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\Common\Persistence\ObjectManager;

use Alex\DoctrineExtraBundle\Graphviz\Pass\ImportMetadataPass;
use Alex\DoctrineExtraBundle\Graphviz\Pass\InheritancePass;
use Alex\DoctrineExtraBundle\Graphviz\Pass\ShortNamePass;

use Alom\Graphviz\Digraph;

class DoctrineMetadataGraph extends Digraph
{
    public function __construct(ObjectManager $manager)
    {
        parent::__construct('G');

        $this->attr('node', array(
            'shape' => 'record'
        ));
        $this->set('rankdir', 'LR');

        $data = $this->prepareData($this->createData($manager));

        $clusters = array();

        foreach ($data['entities'] as $class => $entity) {
            try{
                $clusterName = $this->getCluster($class);
                if (!isset($clusters[$clusterName])) {
                    $clusters[$clusterName] = $this->subgraph('cluster_'.$clusterName)
                        ->set('label', $clusterName)
                        ->set('shape', 'none')
                        ->set('style', 'filled')
                        ->set('color', '#eeeeee')
                        ->attr('node', array(
                            'style' => 'filled',
                            'color' => '#eecc88',
                            'fillcolor' => '#FCF0AD',
                        ))
                    ;
                }
            }catch(\OutOfBoundsException $e){
                    continue;
            }

            $label = $this->getEntityLabel($class, $entity);
            $clusters[$clusterName]->node($class, array('label' => $label));
        }

        foreach ($data['relations'] as $association) {
            $attr = array();
            switch ($association['type']) {
                case 'one_to_one':
                case 'one_to_many':
                case 'many_to_one':
                case 'many_to_many':
                    $attr['color'] = '#88888888';
                    $attr['arrowhead'] = 'none';
                    break;
                case 'extends':
            }

            $this->edge(array($association['from'], $association['to']), $attr);
        }
    }

    private function createData(ObjectManager $manager)
    {
        $data = array('entities' => array(), 'relations' => array());
        $passes = array(
            new ImportMetadataPass(),
            new InheritancePass(),
            new ShortNamePass()
        );

        foreach ($passes as $pass) {
            $data = $pass->process($manager->getMetadataFactory(), $data);
        }

        return $data;
    }

    private function getEntityLabel($class, $entity)
    {
        $result = '{{<__class__>'.$class.'|';
        foreach ($entity['associations'] as $name => $val) {
            $result .= '<'.$name.'> '.$name.' : '.$val.'|';
        }
        foreach ($entity['fields'] as $name => $val) {
            $result .= $name.' : '.$val.'|';
        }
        $result = rtrim($result, '|');
        $result .= '}}';

        return $result;
    }

    private function getCluster($entityName)
    {
        $exp = explode(':', $entityName);

        if (count($exp) !== 2) {
            throw new \OutOfBoundsException(sprintf('Unexpected count of ":" in entity name. Expected one ("AcmeDemoBundle:User"), got %s ("%s").', count($exp), $entityName));
        }

        return $exp[0];
    }

    private function getBundleName($name)
    {
        $name = explode('\\', $name);
        $bundleName = '';
        foreach($name as $n){
            $bundleName .= ucfirst($n);
            if(substr_count($n, 'Bundle') > 0){
                break;
            }
        }
        return $bundleName;
    }

    private function getEntityName($originalName)
    {
        $name = explode('\\', $originalName);
        foreach($name as $k => $n){
            unset($name[$k]);
            if(substr_count($n, 'Bundle') > 0){
                unset($name[$k + 1]);
                break;
            }
        }
        return implode('', $name);
    }

    private function transformName($name)
    {
        if(substr_count($name, ':') < 1 && substr_count($name, 'Bundle') > 0){
            return "{$this->getBundleName($name)}:{$this->getEntityName($name)}";
        }
        return $name;
    }

    private function prepareData(array $data){
        foreach($data as $key => $value){
            unset($data[$key]);
            $newKey = $this->transformName($key);
            $data[$newKey] = $value;

            if(is_array($value)){
                $data[$newKey] = $this->prepareData($value);
            }else{
                $data[$newKey] = $this->transformName($value);
            }
        }
        return $data;
    }

}
