<?php
declare(strict_types=1);
namespace app\core\database;
use InvalidArgumentException;
// WHERE com parâmetros: nenhum valor fornecido pelo usuário é concatenado ao SQL.
class Where {
    private array $conditions = [];
    public static function identifier(string $name): string {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/D', $name)) throw new InvalidArgumentException('Identificador SQL inválido.');
        return $name;
    }
    public function addCondition(string $logic, string $field, string $operator, mixed $value): void {
        $logic = strtoupper($logic); $operator = strtoupper($operator);
        if (!in_array($logic,['AND','OR'],true) || !in_array($operator,['=','!=','<>','>','>=','<','<=','LIKE','NOT LIKE','IN','NOT IN','BETWEEN','NOT BETWEEN','IS','IS NOT'],true)) throw new InvalidArgumentException('Operador inválido.');
        self::identifier($field);
        $this->conditions[] = [$logic,$field,$operator,$value];
    }
    public function addLike(string $l,string $f,mixed $v): void { $this->addCondition($l,$f,'LIKE','%'.$v.'%'); }
    public function addNotLike(string $l,string $f,mixed $v): void { $this->addCondition($l,$f,'NOT LIKE','%'.$v.'%'); }
    public function addIn(string $l,string $f,array $v): void { $this->addCondition($l,$f,'IN',$v); }
    public function addNotIn(string $l,string $f,array $v): void { $this->addCondition($l,$f,'NOT IN',$v); }
    public function addBetween(string $l,string $f,mixed $a,mixed $b): void { $this->addCondition($l,$f,'BETWEEN',[$a,$b]); }
    public function addNotBetween(string $l,string $f,mixed $a,mixed $b): void { $this->addCondition($l,$f,'NOT BETWEEN',[$a,$b]); }
    public function getConditions(): array { return $this->conditions; }
    public function clearConditions(): void { $this->conditions=[]; }
    public function compile(): array {
        $parts=[]; $params=[];
        foreach ($this->conditions as [$logic,$field,$op,$value]) {
            if ($value === null) {
                if (!in_array($op,['=','!=','<>','IS','IS NOT'],true)) throw new InvalidArgumentException('Comparação nula inválida.');
                $part = $field . (in_array($op,['=','IS'],true) ? ' IS NULL' : ' IS NOT NULL');
            } elseif (in_array($op,['IN','NOT IN'],true)) {
                if (!is_array($value)) throw new InvalidArgumentException('Lista esperada.');
                $part = !$value ? ($op==='IN' ? '1=0':'1=1') : $field.' '.$op.' ('.implode(',',array_fill(0,count($value),'?')).')';
                array_push($params,...array_values($value));
            } elseif (in_array($op,['BETWEEN','NOT BETWEEN'],true)) {
                if (!is_array($value) || count($value)!==2) throw new InvalidArgumentException('Intervalo inválido.');
                $part="$field $op ? AND ?"; array_push($params,...array_values($value));
            } else { $part="$field $op ?"; $params[]=$value; }
            $parts[] = ($parts ? ' '.$logic.' ' : '') . $part;
        }
        return [$parts ? ' WHERE '.implode('',$parts) : '',$params];
    }
    public function build(): string { return $this->compile()[0]; }
}
