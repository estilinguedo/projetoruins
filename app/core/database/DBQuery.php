<?php
declare(strict_types=1);
namespace app\core\database;
use PDO;
use InvalidArgumentException;
// API encadeada do modelo, corrigida para usar prepared statements no WHERE.
class DBQuery {
    protected PDO $conn;
    private string $tableName;
    private array $fieldsName;
    private array $primaryKeys;
    private Where $where;
    private array $joins=[];
    public function __construct(string $tableName,string $fieldsNames='*',mixed $primaryKeys=[]) {
        $this->tableName=Where::identifier($tableName);
        $this->fieldsName=array_map('trim',explode(',',$fieldsNames));
        foreach($this->fieldsName as $f) if($f!=='*') Where::identifier($f);
        $this->primaryKeys=(array)$primaryKeys;
        $this->conn=DBConnection::getConnection(); $this->where=new Where();
    }
    public function addCondition(string $l,string $f,string $o,mixed $v): self { $this->where->addCondition($l,$f,$o,$v); return $this; }
    public function addLike(string $l,string $f,mixed $v): self { $this->where->addLike($l,$f,$v); return $this; }
    public function addNotLike(string $l,string $f,mixed $v): self { $this->where->addNotLike($l,$f,$v); return $this; }
    public function addIn(string $l,string $f,array $v): self { $this->where->addIn($l,$f,$v); return $this; }
    public function addNotIn(string $l,string $f,array $v): self { $this->where->addNotIn($l,$f,$v); return $this; }
    public function addBetween(string $l,string $f,mixed $a,mixed $b): self { $this->where->addBetween($l,$f,$a,$b); return $this; }
    public function addNotBetween(string $l,string $f,mixed $a,mixed $b): self { $this->where->addNotBetween($l,$f,$a,$b); return $this; }
    public function clearWhere(): self { $this->where->clearConditions(); return $this; }
    public function clearJoins(): self { $this->joins=[]; return $this; }
    public function addJoin(string $type,string $a,string $fa,string $op,mixed $b,string $fb): self {
        $type=strtoupper($type);
        if(!in_array($type,['INNER','LEFT','RIGHT'],true) || !in_array($op,['=','!=','<>'],true)) throw new InvalidArgumentException('Junção inválida.');
        $b=$b instanceof self ? $b->getTableName() : $b;
        foreach([$a,$fa,$b,$fb] as $id) Where::identifier($id);
        $this->joins[]=" $type JOIN $b ON $a.$fa $op $b.$fb"; return $this;
    }
    public function select(): array {
        [$where,$params]=$this->where->compile();
        try { $s=$this->conn->prepare('SELECT '.implode(',',$this->fieldsName).' FROM '.$this->tableName.implode('',$this->joins).$where); $s->execute($params); return $s->fetchAll(); }
        finally { $this->clearWhere()->clearJoins(); }
    }
    public function insert(array $values): string {
        if(!$values) throw new InvalidArgumentException('Valores obrigatórios.');
        if(array_keys($values)===range(0,count($values)-1)) $values=array_combine($this->fieldsName,$values);
        foreach(array_keys($values) as $f) Where::identifier($f);
        $s=$this->conn->prepare('INSERT INTO '.$this->tableName.' ('.implode(',',array_keys($values)).') VALUES ('.implode(',',array_fill(0,count($values),'?')).')');
        $s->execute(array_values($values)); $this->clearWhere()->clearJoins();
        return $this->conn->lastInsertId();
    }
    private function mutationWhere(array $values): array {
        if(!$this->where->getConditions()) {
            if(!$this->primaryKeys) throw new InvalidArgumentException('Mutação exige filtro.');
            foreach($this->primaryKeys as $pk) {
                if(!isset($values[$pk])) throw new InvalidArgumentException('Chave primária obrigatória.');
                $this->where->addCondition('AND',$pk,'=',$values[$pk]);
            }
        }
        return $this->where->compile();
    }
    public function update(array $values): int {
        [$where,$params]=$this->mutationWhere($values); $set=[]; $bound=[];
        foreach($values as $f=>$v) if(!in_array($f,$this->primaryKeys,true)) { $set[]=Where::identifier($f).'=?'; $bound[]=$v; }
        if(!$set) throw new InvalidArgumentException('Nenhum campo informado.');
        try { $s=$this->conn->prepare('UPDATE '.$this->tableName.' SET '.implode(',',$set).$where); $s->execute(array_merge($bound,$params)); return $s->rowCount(); }
        finally { $this->clearWhere()->clearJoins(); }
    }
    public function delete(array $values=[]): int {
        [$where,$params]=$this->mutationWhere($values);
        try { $s=$this->conn->prepare('DELETE FROM '.$this->tableName.$where); $s->execute($params); return $s->rowCount(); }
        finally { $this->clearWhere()->clearJoins(); }
    }
    public function getConn(): PDO { return $this->conn; }
    public function getTableName(): string { return $this->tableName; }
}
