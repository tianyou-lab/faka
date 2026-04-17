-- 性能优化索引，执行一次即可
-- think_fl.mlm 用于 JOIN think_category_group
ALTER TABLE `think_fl` ADD INDEX IF NOT EXISTS `idx_mlm` (`mlm`);

-- think_mail 卡密查询：mis_use + mpid 组合查询
ALTER TABLE `think_mail` ADD INDEX IF NOT EXISTS `idx_mis_use_mpid` (`mis_use`, `mpid`);

-- think_child_fl.goodid 用于分站商品关联查询
ALTER TABLE `think_child_fl` ADD INDEX IF NOT EXISTS `idx_goodid` (`goodid`);

-- think_member_group_price.goodid
ALTER TABLE `think_member_group_price` ADD INDEX IF NOT EXISTS `idx_goodid` (`goodid`);

-- think_member_price.goodid
ALTER TABLE `think_member_price` ADD INDEX IF NOT EXISTS `idx_goodid` (`goodid`);
