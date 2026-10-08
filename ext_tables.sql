#
# Table structure for table 'tx_jobboard_domain_model_job'
#
CREATE TABLE tx_jobboard_domain_model_job
(
	address int(11) unsigned DEFAULT '0' NOT NULL,
	# Legacy single salary grade (former TCA "group" field), replaced by the
	# MM relation "salary_grades". Only kept, so the upgrade wizard
	# "jweilandJobboardSalaryGradeToSalaryGradesMigration" can read it.
	# Will be removed in a future version.
	salary_grade text,
	salary_min decimal(10, 2) DEFAULT '0.00' NOT NULL,
	salary_max decimal(10, 2) DEFAULT '0.00' NOT NULL
);

#
# Table structure for table 'tx_jobboard_domain_model_salarygrade'
#
CREATE TABLE tx_jobboard_domain_model_salarygrade
(
	flat_amount decimal(10, 2) DEFAULT '0.00' NOT NULL,
	# Not derived from TCA, as ctrl.sortby is not set. Required as
	# foreign_sortby of the inline relation salarytable.salary_grades.
	sorting int(11) unsigned DEFAULT '0' NOT NULL
);

#
# Table structure for table 'tx_jobboard_domain_model_salarystep'
#
CREATE TABLE tx_jobboard_domain_model_salarystep
(
	amount decimal(10, 2) DEFAULT '0.00' NOT NULL
);
