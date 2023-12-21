import Ajv from "ajv";
import merge from "deepmerge";
import { set as _set } from "lodash";

const setError = (error, schema) => {
  // convert property accessor (.xxx[].xxx) notation to jsonPointers notation
  if (error.instancePath.charAt(0) === ".") {
    error.instancePath = error.instancePath.replace(/[.[]/gi, "/");
    error.instancePath = error.instancePath.replace(/[\]]/gi, "");
  }
  const instancePathParts = error.instancePath.split("/").slice(1);
  let instancePath = error.instancePath.slice(1).replace(/\//g, ".");
  const type = findTypeInSchema(schema, instancePathParts);

  let errorToSet;
  if (type === "array" || type === "allOf" || type === "oneOf") {
    errorToSet = { _error: error.message };
  } else {
    errorToSet = error.message;
  }

  let errors = {};
  _set(errors, instancePath, errorToSet);
  return errors;
};

const findTypeInSchema = (schema, instancePath) => {
  if (!schema) {
    return;
  } else if (instancePath.length === 0 && schema.hasOwnProperty("type")) {
    return schema.type;
  } else {
    if (schema.type === "array") {
      return findTypeInSchema(schema.items, instancePath.slice(1));
    } else if (schema.hasOwnProperty("allOf")) {
      if (instancePath.length === 0) return "allOf";
      schema = { ...schema, ...merge.all(schema.allOf) };
      delete schema.allOf;
      return findTypeInSchema(schema, instancePath);
    } else if (schema.hasOwnProperty("oneOf")) {
      if (instancePath.length === 0) return "oneOf";
      schema.oneOf.forEach(item => {
        let type = findTypeInSchema(item, instancePath);
        if (type) {
          return type;
        }
      });
    } else {
      return findTypeInSchema(
        schema.properties[instancePath[0]],
        instancePath.slice(1)
      );
    }
  }
};

const buildSyncValidation = (schema, ajvParam = null) => {
  let ajv = ajvParam;
  if (ajv === null) {
    ajv = new Ajv({
      allErrors: true,
        strict: false
    });
  }
  return values => {
    const valid = ajv.validate(schema, values);
    if (valid) {
      return {};
    }
    const ajvErrors = ajv.errors;

    let errors = ajvErrors.map(error => {
      return setError(error, schema);
    });
    // We need at least two elements
    errors.push({});
    errors.push({});
    return merge.all(errors);
  };
};

export default buildSyncValidation;

export { setError };
