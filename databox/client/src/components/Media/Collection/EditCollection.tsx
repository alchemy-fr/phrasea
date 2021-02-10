import AbstractEdit from "../AbstractEdit";
import {Collection} from "../../../types";
import {getCollection} from "../../../api/collection";

export default class EditCollection extends AbstractEdit<Collection> {
    async loadItem() {
        return await getCollection(this.props.id);
    }

    renderForm(): React.ReactNode {
        return '';
    }
}
