<?php
$host = '192.168.1.171';           	// 数据库主机
$db   = 'charset_demo_db';     		// 数据库名
$user = 'root';       				// 数据库用户名
$pass = '123456';       			// 数据库密码
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

function randomString($length = 10) {
    return substr(str_shuffle(str_repeat('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', 10)), 0, $length);
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    $total = 10000;
    $batchSize = 100;
    $sql = "INSERT INTO single_table 
        (key1, key2, key3, common_field, key_part1, key_part2, key_part3) 
        VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);

    $currentKey2 = 1; // key2为唯一约束 必须唯一

    for ($i = 0; $i < $total; $i++) {
        $key1 = randomString(8);
        $key2 = $currentKey2++; // 唯一整数
        $key3 = randomString(8);
        $common_field = randomString(8);
        $key_part1 = randomString(6);
        $key_part2 = randomString(6);
        $key_part3 = randomString(6);

        $stmt->execute([$key1, $key2, $key3, $common_field, $key_part1, $key_part2, $key_part3]);
        // 批量插入时可考虑事务
        if ($i % $batchSize === 0) {
            echo "Inserted $i records...\n";
        }
    }
    echo "插入完成!\n";
} catch (PDOException $e) {
    die("数据库连接失败: " . $e->getMessage());
}