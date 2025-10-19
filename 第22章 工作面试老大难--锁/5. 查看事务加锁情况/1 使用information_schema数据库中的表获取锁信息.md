# 1 使用`information_schema`数据库中的表获取锁信息

在数据库`information_schema`中,有几个与事务和锁紧密相关的表,具体如下:

- `INNODB_TRX`

    该表存储了InnoDB存储引擎当前正在执行的事务信息,包括:
    
    - 事务id
      - 若没有为该事务分配唯一的事务id,则会输出该事务对应的内存结构的指针
    - 事务状态
      - 比如事务是正在运行还是在等待获取某个锁
      - 事务正在执行的语句
      - 事务是何时开启的
      - 等

    在一个会话中执行事务T1:
    
    ```
    mysql> BEGIN;
    Query OK, 0 rows affected (0.00 sec)
    
    mysql> SELECT * FROM hero WHERE number = 8 FOR UPDATE;
    +--------+---------+---------+
    | number | name    | country |
    +--------+---------+---------+
    |      8 | c曹操   | 魏      |
    +--------+---------+---------+
    1 row in set (0.00 sec)
    ```
    
    再到另一个会话中查询`INNODB_TRX`表:
    
    ```
    mysql> SELECT * FROM information_schema.INNODB_TRX\G;
    *************************** 1. row ***************************
                        trx_id: 69935
                     trx_state: RUNNING
                   trx_started: 2025-10-19 15:21:23
         trx_requested_lock_id: NULL
              trx_wait_started: NULL
                    trx_weight: 2
           trx_mysql_thread_id: 13
                     trx_query: NULL
           trx_operation_state: NULL
             trx_tables_in_use: 0
             trx_tables_locked: 1
              trx_lock_structs: 2
         trx_lock_memory_bytes: 1128
               trx_rows_locked: 1
             trx_rows_modified: 0
       trx_concurrency_tickets: 0
           trx_isolation_level: REPEATABLE READ
             trx_unique_checks: 1
        trx_foreign_key_checks: 1
    trx_last_foreign_key_error: NULL
     trx_adaptive_hash_latched: 0
     trx_adaptive_hash_timeout: 0
              trx_is_read_only: 0
    trx_autocommit_non_locking: 0
           trx_schedule_weight: NULL
    1 row in set (0.00 sec)
    
    ERROR: 
    No query specified
    ```
    
    从执行结果可以看到:
    
    - 当前系统中有一个事务id为69935的事务
    - 它的状态为"正在运行"(`RUNNING`)
    - 隔离级别为`REPEATABLE READ`
    
    这里重点关注:
    
    - `trx_tables_locked`
    - `trx_rows_locked`
    - `trx_lock_structs`
    
    其中:
    
    - `trx_tables_locked`: 表示该事务目前加的表级锁的数量
    - `trx_rows_locked`: 表示该事务目前加的行级锁的数量
      - 注意: 这里不包括隐式锁的数量
    - `trx_lock_structs`: 表示该事务在内存中生成的锁结构的数量

- `INNODB_LOCKS`

    该表记录了一些锁信息，主要包括以下2个方面的锁信息:
    
    - 若一个事务想要获取某个锁但未获取到,则记录该锁信息
    - 若一个事务获取到了某个锁,但是这个锁阻塞了别的事务,则记录该锁信息

    该表在MySQL8.0版本中被废弃,建议使用`performance_schema.data_locks`表来获取锁信息

    ```
    mysql> SELECT * FROM performance_schema.data_locks\G;
    *************************** 1. row ***************************
                   ENGINE: INNODB
           ENGINE_LOCK_ID: 140020962821712:244:1186:140020967086624
    ENGINE_TRANSACTION_ID: 69935
                THREAD_ID: 59
                 EVENT_ID: 23
            OBJECT_SCHEMA: join_demo
              OBJECT_NAME: hero
           PARTITION_NAME: NULL
        SUBPARTITION_NAME: NULL
               INDEX_NAME: NULL
    OBJECT_INSTANCE_BEGIN: 140020967086624
                LOCK_TYPE: TABLE
                LOCK_MODE: IX
              LOCK_STATUS: GRANTED
                LOCK_DATA: NULL
    *************************** 2. row ***************************
                   ENGINE: INNODB
           ENGINE_LOCK_ID: 140020962821712:244:30:4:4:140020967083712
    ENGINE_TRANSACTION_ID: 69935
                THREAD_ID: 59
                 EVENT_ID: 23
            OBJECT_SCHEMA: join_demo
              OBJECT_NAME: hero
           PARTITION_NAME: NULL
        SUBPARTITION_NAME: NULL
               INDEX_NAME: PRIMARY
    OBJECT_INSTANCE_BEGIN: 140020967083712
                LOCK_TYPE: RECORD
                LOCK_MODE: X,REC_NOT_GAP
              LOCK_STATUS: GRANTED
                LOCK_DATA: 8
    2 rows in set (0.02 sec)
    
    ERROR: 
    No query specified
    ```

    注意: `performance_schema.data_locks`表中记录的是当前系统中所有事务的锁信息,而不仅仅是某个事务的锁信息(这一点和`INNODB_LOCKS`表是不同的)

- `INNODB_LOCK_WAITS`

    表明每个阻塞的事务是因为获取不到哪个事务持有的锁而阻塞

    该表在MySQL8.0版本中被废弃,建议使用`performance_schema.data_lock_waits`表来获取锁等待信息
    
    再到另一个会话中开启事务T2,然后执行:
    
    ```
    mysql> BEGIN;
    Query OK, 0 rows affected (0.00 sec)
    
    mysql> SELECT * FROM hero WHERE number =8 FOR UPDATE; # 进入阻塞状态
    ```

    然后查询`performance_schema.data_lock_waits`表:
    
    ```
    mysql> SELECT * FROM performance_schema.data_lock_waits\G;
    *************************** 1. row ***************************
                              ENGINE: INNODB
           REQUESTING_ENGINE_LOCK_ID: 140020962822520:50:30:4:4:140020967089984
    REQUESTING_ENGINE_TRANSACTION_ID: 69937
                REQUESTING_THREAD_ID: 60
                 REQUESTING_EVENT_ID: 23
    REQUESTING_OBJECT_INSTANCE_BEGIN: 140020967089984
             BLOCKING_ENGINE_LOCK_ID: 140020962821712:244:30:4:4:140020967083712
      BLOCKING_ENGINE_TRANSACTION_ID: 69935
                  BLOCKING_THREAD_ID: 59
                   BLOCKING_EVENT_ID: 23
      BLOCKING_OBJECT_INSTANCE_BEGIN: 140020967083712
    1 row in set (0.01 sec)
    
    ERROR: 
    No query specified
    ```
    
    其中:
    
    - `REQUESTING_ENGINE_LOCK_ID`: 表示当前阻塞的锁id
    - `REQUESTING_ENGINE_TRANSACTION_ID`: 表示当前阻塞的事务id
    - `BLOCKING_ENGINE_LOCK_ID`: 表示持有锁的锁id
    - `BLOCKING_ENGINE_TRANSACTION_ID`: 表示持有锁的事务id
