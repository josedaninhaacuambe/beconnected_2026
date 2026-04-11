-- Script para verificar e corrigir associações entre lojas e usuários
-- Execute no MySQL para diagnosticar problemas de associação

-- 1. Verificar lojas sem user_id
SELECT 'Lojas sem user_id:' as info, COUNT(*) as count FROM stores WHERE user_id IS NULL AND deleted_at IS NULL;

-- 2. Verificar usuários store_owner sem loja
SELECT 'Proprietários sem loja:' as info, COUNT(*) as count
FROM users u
WHERE u.role = 'store_owner'
AND NOT EXISTS (SELECT 1 FROM stores s WHERE s.user_id = u.id AND s.deleted_at IS NULL);

-- 3. Listar detalhes das lojas sem user_id
SELECT 'Lojas sem user_id - Detalhes:' as info, s.id, s.name, s.email, s.created_at
FROM stores s
WHERE s.user_id IS NULL AND s.deleted_at IS NULL;

-- 4. Listar proprietários sem loja
SELECT 'Proprietários sem loja - Detalhes:' as info, u.id, u.name, u.email, u.created_at
FROM users u
WHERE u.role = 'store_owner'
AND NOT EXISTS (SELECT 1 FROM stores s WHERE s.user_id = u.id AND s.deleted_at IS NULL);

-- 5. Tentar correção automática baseada em email/nome similar
-- (Este UPDATE só será executado se você descomentá-lo)
-- UPDATE stores s
-- INNER JOIN users u ON (
--     (s.email = u.email AND u.role = 'store_owner')
--     OR (s.name LIKE CONCAT('%', u.name, '%') AND u.role = 'store_owner')
-- )
-- SET s.user_id = u.id
-- WHERE s.user_id IS NULL
-- AND s.deleted_at IS NULL
-- AND NOT EXISTS (SELECT 1 FROM stores s2 WHERE s2.user_id = u.id AND s2.deleted_at IS NULL);