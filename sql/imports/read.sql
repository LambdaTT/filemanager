SELECT
    imp.*, -- 1 a 10
    CASE
      WHEN imp.do_status = 'P' THEN 'Pendente'
      WHEN imp.do_status = 'R' THEN 'Processando'
      WHEN imp.do_status = 'D' THEN 'Finalizada'
      WHEN imp.do_status = 'F' THEN 'Falhou'
    END as statusText,
    -- Audit
    DATE_FORMAT(imp.dt_created, '%d/%m/%Y %T') as dtCreated, 
    CONCAT(usrc.ds_first_name, ' ', usrc.ds_last_name) as userCreated,
    fle.ds_filename,
    fle.ds_url as fileUrl,
    fle.ds_content_type as fileMimeType,
    typ.ds_title as importType,
    typ.ds_servicepath,
    typ.ds_servicemethod
  FROM `FMN_FILE_IMPORT` imp
  LEFT JOIN `IAM_USER` usrc ON usrc.id_iam_user = imp.id_iam_user_created
  JOIN `FMN_FILE` fle ON fle.id_fmn_file = imp.id_fmn_file
  JOIN `FMN_IMPORT_TYPE` typ ON typ.id_fmn_type = imp.id_fmn_type