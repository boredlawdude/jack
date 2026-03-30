<?php
declare(strict_types=1);

final class Person
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM people
            WHERE person_id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO people (
                first_name,
                last_name,
                email,
                officephone,
                cellphone,
                title,
                department_id,
                is_active,
                is_town_employee,
                company_id
            ) VALUES (
                :first_name,
                :last_name,
                :email,
                :officephone,
                :cellphone,
                :title,
                :department_id,
                :is_active,
                :is_town_employee,
                :company_id
            )
        ");

        $stmt->execute([
            'first_name'    => $data['first_name'],
            'last_name'     => $data['last_name'],
            'email'         => $data['email'],
            'officephone'  => $data['officephone'],
            'cellphone'    => $data['cellphone'],
            'title'         => $data['title'],
            'department_id' => $data['department_id'] ?: null,
            'is_active'     => $data['is_active'],
            'is_town_employee' => $data['is_town_employee'],
            'company_id'    => $data['company_id'] ?: null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE people
            SET
                first_name = :first_name,
                last_name = :last_name,
                email = :email,
                officephone = :officephone,
                cellphone = :cellphone,
                title = :title,
                department_id = :department_id,
                is_active = :is_active,
                is_town_employee = :is_town_employee
            WHERE person_id = :id
        ");

        $stmt->execute([
            'id'            => $id,
            'first_name'    => $data['first_name'],
            'last_name'     => $data['last_name'],
            'email'         => $data['email'],
            'officephone'  => $data['officephone'],
            'cellphone'    => $data['cellphone'],
            'title'         => $data['title'],
            'department_id' => $data['department_id'] ?: null,
            'is_active'     => $data['is_active'],
            'is_town_employee' => $data['is_town_employee'],
        ]);
    }

    public function allDepartments(): array
    {
        $stmt = $this->pdo->query("
            SELECT department_id, department_name, dept_initials
            FROM departments
            ORDER BY department_name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}